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

class EventMapper {
	public const TABLE = 'cc_events';

	public function __construct(
		private IDBConnection $db,
	) {
	}

	public function byId(int $id): ?array {
		if ($id <= 0) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id', 'event_uuid', 'uid', 'trigger_key', 'context_app', 'created_at')
			->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $this->cast($row);
	}

	public function forUser(string $uid, int $since, int $limit): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id', 'event_uuid', 'trigger_key', 'uid', 'context_app', 'created_at')
			->from(self::TABLE)
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')->setMaxResults($limit)->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return array_map($this->cast(...), $rows);
	}

	public function byIdForUser(int $id, string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id', 'event_uuid', 'trigger_key', 'uid', 'context_app', 'created_at')
			->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $this->cast($row);
	}

	public function byDedupeKey(string $key): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('dedupe_key', $qb->createNamedParameter($key)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $this->cast($row);
	}

	/** @return array{event:array,inserted:bool} */
	public function persist(array $data): array {
		$qb = $this->db->getQueryBuilder();
		$params = [];
		foreach ($data as $column => $value) {
			$params[$column] = $qb->createNamedParameter(
				$value,
				is_int($value) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR,
			);
		}
		try {
			$qb->insert(self::TABLE)->values($params)->executeStatement();
		} catch (DbException $error) {
			if (!in_array($error->getReason(), [
				DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION,
				DbException::REASON_CONSTRAINT_VIOLATION,
			], true)) {
				throw $error;
			}
			$existing = $this->byDedupeKey($data['dedupe_key']);
			if ($existing === null) {
				throw $error;
			}
			return ['event' => $existing, 'inserted' => false];
		}
		$data['id'] = (int)$this->db->lastInsertId(self::TABLE);
		return ['event' => $data, 'inserted' => true];
	}

	public function prune(int $before): int {
		$qb = $this->db->getQueryBuilder();
		return $qb->delete(self::TABLE)
			->where($qb->expr()->lt('created_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function cast(array $row): array {
		$row['id'] = (int)$row['id'];
		$row['created_at'] = (int)$row['created_at'];
		return $row;
	}
}
