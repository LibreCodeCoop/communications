<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CampaignMapper {
	public const TABLE = 'cc_campaigns';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/** @return \Generator<int,array> */
	public function scanRows(): \Generator {
		$before = null;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from(self::TABLE)->orderBy('id', 'DESC')->setMaxResults(250);
			if ($before !== null) {
				$qb->where($qb->expr()->lt('id', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
			}
			$rs = $qb->executeQuery();
			$rows = $rs->fetchAllAssociative();
			$rs->closeCursor();
			foreach ($rows as $row) {
				$before = (int)$row['id'];
				yield $row;
			}
		} while (count($rows) === 250);
	}

	public function guestCandidates(): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('guest_visible', $qb->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->eq('presentation', $qb->createNamedParameter('banner')))
			->andWhere($qb->expr()->eq('audience_type', $qb->createNamedParameter('all')))
			->andWhere($qb->expr()->eq('trigger_key', $qb->createNamedParameter('nextcloud.time.window')))
			->andWhere($qb->expr()->eq('category', $qb->createNamedParameter('institutional')))
			->orderBy('priority', 'DESC')->addOrderBy('manual_order', 'ASC')
			->setMaxResults(30)->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}

	public function byId(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	public function activeForTrigger(string $trigger): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('trigger_key', $qb->createNamedParameter($trigger)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->orderBy('priority', 'DESC')->addOrderBy('id', 'ASC')
			->setMaxResults(100)->executeQuery();
		$rows = $result->fetchAllAssociative();
		$result->closeCursor();
		return $rows;
	}

	public function insert(array $validated): int {
		$qb = $this->db->getQueryBuilder();
		$values = [];
		foreach ($validated as $column => $value) {
			$values[$column] = $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR);
		}
		$qb->insert(self::TABLE)->values($values)->executeStatement();
		return (int)$this->db->lastInsertId(self::TABLE);
	}

	public function update(int $id, array $validated): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		foreach ($validated as $column => $value) {
			$qb->set($column, $qb->createNamedParameter($value, is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR));
		}
		$qb->executeStatement();
	}

	public function persistOrder(array $rows): void {
		$this->db->beginTransaction();
		try {
			foreach ($rows as $i => $campaign) {
				$order = ($i + 1) * 10;
				if ((int)$campaign['manual_order'] === $order) {
					continue;
				}
				$qb = $this->db->getQueryBuilder();
				$qb->update(self::TABLE)
					->set('manual_order', $qb->createNamedParameter($order, IQueryBuilder::PARAM_INT))
					->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$campaign['id'], IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}
			$this->db->commit();
		} catch (\Throwable $error) {
			$this->db->rollBack();
			throw $error;
		}
	}

	public function purgeArchived(int $id): void {
		$this->db->beginTransaction();
		try {
			foreach (['cc_board_comments', 'cc_board_pub', 'cc_board_sent', 'cc_inbox_reads', 'cc_deliveries', 'cc_leases'] as $table) {
				$qb = $this->db->getQueryBuilder();
				$qb->delete($table)
					->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
					->executeStatement();
			}
			$qb = $this->db->getQueryBuilder();
			$deleted = $qb->delete(self::TABLE)
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('archived')))
				->executeStatement();
			if ($deleted !== 1) {
				throw new CampaignConflictException('Campaign was modified during archival deletion');
			}
			$this->db->commit();
		} catch (\Throwable $error) {
			$this->db->rollBack();
			throw $error;
		}
	}

	public function setStatus(int $id, string $status, int $now): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->set('status', $qb->createNamedParameter($status))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		if ($status === 'active') {
			$qb->set('published_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))->executeStatement();
	}

	/** Active native event keys, with optional campaign exclusion. */
	public function activeNativeEventKeys(?int $excludeCampaignId = null, ?int $limit = null): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('trigger_key')->from(self::TABLE)
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->like('trigger_key', $qb->createNamedParameter('php_event.%')))
			->groupBy('trigger_key');
		if ($excludeCampaignId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeCampaignId, IQueryBuilder::PARAM_INT)));
		}
		if ($limit !== null) {
			$qb->setMaxResults($limit);
		}
		$result = $qb->executeQuery();
		$keys = $result->fetchFirstColumn();
		$result->closeCursor();
		return $keys;
	}
}
