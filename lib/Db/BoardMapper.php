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
	public function hasSentReceipt(int $id, int $version, string $uid): bool {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id')->from('cc_board_sent')
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['claimed', 'sent'], IQueryBuilder::PARAM_STR_ARRAY)))
			->setMaxResults(1)->executeQuery();
		$result = $rs->fetchOne() !== false;
		$rs->closeCursor();
		return $result;
	}

	public function markRead(string $uid, int $id, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('read_at')->from('cc_inbox_reads')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$existing = $rs->fetchOne();
		$rs->closeCursor();
		if ($existing !== false) {
			return true;
		}
		$insert = $this->db->getQueryBuilder();
		$insert->insert('cc_inbox_reads')->values([
			'uid' => $insert->createNamedParameter($uid),
			'campaign_id' => $insert->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			'read_at' => $insert->createNamedParameter($now, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return true;
	}

	public function commentPage(int $campaignId, int $before): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uid', 'message', 'created_at')->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));
		if ($before > 0) {
			$qb->andWhere($qb->expr()->lt('id', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		$rs = $qb->orderBy('id', 'DESC')->setMaxResults(21)->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}

	public function hasRecentComment(int $id, string $uid, int $since): bool {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$found = $rs->fetchOne() !== false;
		$rs->closeCursor();
		return $found;
	}

	public function insertComment(int $id, string $uid, string $message, int $now): int {
		$qb = $this->db->getQueryBuilder();
		$qb->insert(self::TABLE)->values([
			'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			'uid' => $qb->createNamedParameter($uid),
			'message' => $qb->createNamedParameter($message),
			'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			'updated_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return (int)$this->db->lastInsertId(self::TABLE);
	}

	public function commentAuthor(int $campaignId, int $commentId): string|false {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('uid')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$uid = $rs->fetchOne();
		$rs->closeCursor();
		return $uid === false ? false : (string)$uid;
	}

	public function deleteComment(int $campaignId, int $commentId, int $now): bool {
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('deleted_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeStatement() === 1;
	}

	public function commentForNotification(int $id): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('campaign_id', 'uid')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $row;
	}
}
