<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** Persistent Inbox receipts and comments. Authorization remains in BoardService. */
class BoardMapper {
	public const TABLE = 'cc_board_comments';

	public function __construct(private IDBConnection $db) {
	}

	/** Recorded event-triggered Inbox receipt, indexed by campaign and epoch. */
	public function eventInboxReceipts(string $uid):array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('campaign_id', 'version')->from('cc_board_sent')->groupBy('campaign_id', 'version')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter('inbox')))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['claimed','sent'], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeQuery();
		$keys = [];
		foreach ($rs->fetchAllAssociative() as $row) {
			$keys[(int)$row['campaign_id'] . ':' . (int)$row['version']] = true;
		}
		$rs->closeCursor();
		return $keys;
	}

	/** Keyset pagination of true impressions, preserving the oldest receipts. */
	public function deliveredAt(string $uid):array {
		$map = [];
		$before = null;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'campaign_id', 'impression_at')->from('cc_deliveries')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->andWhere($qb->expr()->isNotNull('impression_at'))
				->orderBy('id', 'DESC')->setMaxResults(500);
			if ($before !== null) {
				$qb->andWhere($qb->expr()->lt('id', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
			}
			$rs = $qb->executeQuery();
			$rows = $rs->fetchAllAssociative();
			$rs->closeCursor();
			foreach ($rows as $row) {
				$before = (int)$row['id'];
				$id = (int)$row['campaign_id'];
				$map[$id] = max($map[$id] ?? 0, (int)$row['impression_at']);
			}
		} while (count($rows) === 500);
		return $map;
	}

	/** Keyset pagination of user reading state, no first-2000 truncation. */
	public function readAt(string $uid):array {
		$map = [];
		$after = null;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('campaign_id', 'read_at')->from('cc_inbox_reads')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->orderBy('campaign_id', 'ASC')->setMaxResults(500);
			if ($after !== null) {
				$qb->andWhere($qb->expr()->gt('campaign_id', $qb->createNamedParameter($after, IQueryBuilder::PARAM_INT)));
			}
			$rs = $qb->executeQuery();
			$rows = $rs->fetchAllAssociative();
			$rs->closeCursor();
			foreach ($rows as $row) {
				$after = (int)$row['campaign_id'];
				$map[$after] = (int)$row['read_at'];
			}
		} while (count($rows) === 500);
		return $map;
	}

	public function countComments(int $id): int {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select($qb->func()->count('id'))->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$n = (int)$rs->fetchOne();
		$rs->closeCursor();
		return $n;
	}
}
