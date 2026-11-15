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
}
