<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Db;

use OCA\Communications\Service\EventStore;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Delivery> */
final class DeliveryMapper extends QBMapper {
	public const TABLE = 'cc_deliveries';
	public const CLAIM_TTL = 90;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE, Delivery::class);
	}

	public function ensureDelivery(int $eventId, int $campaignId, string $uid): void {
		$qb = $this->db->getQueryBuilder();
		$rows = [
			'event_id' => $eventId, 'campaign_id' => $campaignId, 'uid' => $uid,
			'state' => 'pending', 'claim_token' => '', 'claim_until' => 0,
			'created_at' => time(),
		];
		foreach ($rows as $column => $value) {
			$rows[$column] = $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR);
		}
		try {
			$qb->insert(self::TABLE)->values($rows)->executeStatement();
		} catch (DbException $error) {
			if (!in_array($error->getReason(), [DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION, DbException::REASON_CONSTRAINT_VIOLATION], true)) {
				throw $error;
			}
			// Another tab/process already created this delivery.
		}
	}

	public function reserveCampaign(string $uid, int $campaignId, int $deliveryId, string $token, int $now): bool {
		$insert = $this->db->getQueryBuilder();
		try {
			$insert->insert('cc_leases')->values([
				'uid' => $insert->createNamedParameter($uid),
				'campaign_id' => $insert->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT),
				'delivery_id' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
				'token' => $insert->createNamedParameter(''),
				'expires_at' => $insert->createNamedParameter(0, IQueryBuilder::PARAM_INT),
			])->executeStatement();
		} catch (DbException $e) {
			if (!in_array($e->getReason(), [DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION, DbException::REASON_CONSTRAINT_VIOLATION], true)) {
				throw $e;
			}
		}
		$qb = $this->db->getQueryBuilder();
		return $qb->update('cc_leases')
			->set('delivery_id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT))
			->set('token', $qb->createNamedParameter($token))
			->set('expires_at', $qb->createNamedParameter($now + self::CLAIM_TTL, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('expires_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->executeStatement() === 1;
	}

	public function releaseCampaign(string $uid, int $campaignId, string $token): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cc_leases')
			->set('expires_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->executeStatement();
	}

	public function getForUser(int $deliveryId, string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $row;
	}

	public function metrics(int $campaignId): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('state', 'impression_at', 'dismissed_at', 'clicked_at')
			->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return [
			'estimatedImpressions' => count(array_filter($rows, fn (array $r): bool => $r['impression_at'] !== null)),
			'dismissals' => count(array_filter($rows, fn (array $r): bool => $r['dismissed_at'] !== null)),
			'clicks' => count(array_filter($rows, fn (array $r): bool => $r['clicked_at'] !== null)),
			'queuedOrClaimed' => count(array_filter($rows, fn (array $r): bool => in_array($r['state'], ['pending','claimed'], true))),
		];
	}

	public function prune(int $before): int {
		$leases = $this->db->getQueryBuilder();
		$leases->delete('cc_leases')
			->where($leases->expr()->lt('expires_at', $leases->createNamedParameter(time() - 86400, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		$qb = $this->db->getQueryBuilder();
		return $qb->delete(self::TABLE)->where($qb->expr()->lt('created_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	public function pendingRows(string $uid): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('d.*', 'e.event_uuid', 'e.trigger_key', 'e.created_at AS event_created_at')
			->from(self::TABLE, 'd')
			->innerJoin('d', EventStore::TABLE, 'e', 'e.id = d.event_id')
			->where($qb->expr()->eq('d.uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->in('d.state', $qb->createNamedParameter(['pending', 'claimed'], IQueryBuilder::PARAM_STR_ARRAY)))
		 // Prefer recently queued deliveries. An ever-growing backlog must not
		 // permanently hide new campaigns behind the first 250 pending records.
			->orderBy('d.id', 'DESC')
			->setMaxResults(250)
			->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}

	public function frequencyRows(string $uid, int $id, int $deliveryId): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('impression_at', 'state', 'claim_until')->from(self::TABLE)
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}

	public function claimDelivery(int $deliveryId, string $uid, string $token, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('state', $qb->createNamedParameter('claimed'))
			->set('claim_token', $qb->createNamedParameter($token))
			->set('claim_until', $qb->createNamedParameter($now + self::CLAIM_TTL, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('state', $qb->createNamedParameter('pending')),
				$qb->expr()->andX(
					$qb->expr()->eq('state', $qb->createNamedParameter('claimed')),
					$qb->expr()->lt('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				)
			))->executeStatement() === 1;
	}

	public function persistTransition(string $uid, int $id, string $token, string $action, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('claim_token', $qb->createNamedParameter($token)));
		if ($action === 'impression') {
			$qb->set('state', $qb->createNamedParameter('shown'))
				->set('impression_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('claimed')));
		} elseif ($action === 'dismiss') {
			$qb->set('state', $qb->createNamedParameter('dismissed'))
				->set('dismissed_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		} else {
			$qb->set('state', $qb->createNamedParameter('clicked'))
				->set('clicked_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				->set('dismissed_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		}
		return $qb->executeStatement() === 1;
	}

	public function legacyRow(string $uid, int $campaignId, string $eventUuid): array|false {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('d.id', 'd.state')->from(self::TABLE, 'd')
			->innerJoin('d', EventStore::TABLE, 'e', 'e.id = d.event_id')
			->where($qb->expr()->eq('d.uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('d.campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.event_uuid', $qb->createNamedParameter($eventUuid)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row;
	}

	public function dismissLegacy(int $id, string $uid, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('state', $qb->createNamedParameter('dismissed'))
			->set('dismissed_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->executeStatement() === 1;
	}
}
