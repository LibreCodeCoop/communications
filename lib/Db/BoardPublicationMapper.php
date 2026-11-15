<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Db;

use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persists publication epochs and channel-delivery attempts. */
class BoardPublicationMapper {
	private const PUB = 'cc_board_pub';
	private const SENT = 'cc_board_sent';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** @return array<string, mixed>|null */
	public function publication(int $id, int $version): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from(self::PUB)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	public function ensurePublication(int $id, int $version): array {
		$existing = $this->publication($id, $version);
		if ($existing !== null) {
			return $existing;
		}
		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert(self::PUB)->values([
				'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				'version' => $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
			])->executeStatement();
		} catch (\OCP\DB\Exception $e) {
			// A second worker may have inserted the same publication meanwhile.
			if ($this->publication($id, $version) === null) {
				throw $e;
			}
		}
		return $this->publication($id, $version);
	}

	public function markPublication(int $id, int $version, string $field, int $value):void {
		if (!in_array($field, ['event_sent','broadcast_done'], true)) {
			throw new \InvalidArgumentException('Invalid publication field');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::PUB)
			->set($field, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)));
		if ($field === 'event_sent') {
			$qb->set('event_sent_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
		}
		$qb->executeStatement();
	}

	public function sentRow(int $id, int $version, string $uid, string $channel, int $eventId = 0): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($channel)))
			->andWhere($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $row;
	}

	public function stateDispatch(int $rowId, string $status, ?string $error = null, int $backoff = 0):void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::SENT)
			->set('status', $qb->createNamedParameter($status))
			->set('claim_until', $qb->createNamedParameter($backoff?time() + $backoff:0, IQueryBuilder::PARAM_INT))
			->set('last_error', $qb->createNamedParameter($error === null?null:mb_substr($error, 0, 248)));
		if ($status === 'sent') {
			$qb->set('sent_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	public function insertDispatch(int $id, int $version, string $uid, string $channel, int $eventId, int $now): void {
		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert(self::SENT)->values([
				'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				'version' => $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT),
				'event_id' => $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT),
				'uid' => $qb->createNamedParameter($uid),
				'channel' => $qb->createNamedParameter($channel),
				'status' => $qb->createNamedParameter('pending'),
				'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			])->executeStatement();
		} catch (DbException $error) {
			if (!in_array($error->getReason(), [
				DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				DbException::REASON_CONSTRAINT_VIOLATION,
			], true) || $this->sentRow($id, $version, $uid, $channel, $eventId) === null) {
				throw $error;
			}
		}
	}

	public function claimDispatch(int $rowId, int $attempt, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::SENT)
			->set('status', $qb->createNamedParameter('claimed'))
			->set('attempts', $qb->createNamedParameter($attempt, IQueryBuilder::PARAM_INT))
			->set('claim_until', $qb->createNamedParameter($now + 90, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->executeStatement() === 1;
	}

	public function pendingCount(int $id, int $version): int {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select($qb->func()->count('id'))->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['retry', 'claimed', 'pending'], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeQuery();
		$result = (int)$rs->fetchOne();
		$rs->closeCursor();
		return $result;
	}

	public function eventRows(int $id, int $version, string $uid, string $channel): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('event_id', 'created_at', 'sent_at', 'status')->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($channel)))
			->andWhere($qb->expr()->gt('event_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}

	public function retryEventIds(int $limit, int $now): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('event_id')->from(self::SENT)->groupBy('event_id')
			->where($qb->expr()->gt('event_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('retry')))
			->andWhere($qb->expr()->lte('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->setMaxResults(max(1, min($limit, 500)))->executeQuery();
		$ids = array_map('intval', $rs->fetchFirstColumn());
		$rs->closeCursor();
		return $ids;
	}

	public function stats(int $id): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('channel', 'status', $qb->func()->count('id') . ' AS total')
			->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->groupBy('channel', 'status')->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}
}
