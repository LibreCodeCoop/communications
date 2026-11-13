<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCA\Communications\BackgroundJob\EventChannelDeliveryJob;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Minimal durable event log: do not persist file paths or document content.
 */
class EventStore {
	public const TABLE = 'cc_events';
	public const RETENTION_SECONDS = 60 * 60 * 24 * 30;

	public function __construct(
		private IDBConnection $db,
		private IJobList $jobs,
		private CampaignRepository $campaigns,
	) {
	}

	private function scheduleChannels(array $event):void {
		if (in_array($event['trigger_key'], ['nextcloud.board.published','nextcloud.time.window'], true)) {
			return;
		}
		// Do not put content or recipient addresses in the job payload, only an
		// internal event ID. The worker revalidates campaign, audience and version.
		foreach ($this->campaigns->activeForTrigger($event['trigger_key']) as $campaign) {
			if ($campaign['board_visible'] || $campaign['notify_users'] || $campaign['notify_activity']
	   || $campaign['notify_email'] || $campaign['gateway_channels'] !== []) {
				$this->jobs->add(EventChannelDeliveryJob::class, ['eventId' => (int)$event['id']]);
				return;
			}
		}
	}

	/** Trusted worker lookup; event ID is never accepted directly from a client. */
	public function byId(int $id):?array {
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
		return $row === false?null:$this->cast($row);
	}

	/**
	 * @return array{id:int, event_uuid:string, uid:string, trigger_key:string, context_app:string, created_at:int}
	 */
	public function record(string $uid, string $trigger, string $contextApp = 'any', ?string $idempotencyKey = null): array {
		if ($uid === '' || mb_strlen($uid) > 64 || $trigger === '' || mb_strlen($trigger) > 64) {
			throw new \InvalidArgumentException('Invalid event subject.');
		}
		if (!preg_match('/^[a-z0-9_-]{1,32}$/', $contextApp)) {
			throw new \InvalidArgumentException('Invalid app context.');
		}
		// Hash the caller's external ID to avoid persisting untrusted identifiers.
		$dedupeKey = $idempotencyKey === null
		 ? bin2hex(random_bytes(32))
		 : hash('sha256', $uid . "\0" . $trigger . "\0" . $idempotencyKey);
		$existing = $this->byDedupeKey($dedupeKey);
		if ($existing !== null) {
			// A webhook replay may recover a job submission that had failed.
			$this->scheduleChannels($existing);
			return $existing;
		}
		$hex = bin2hex(random_bytes(16));
		$uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
		$data = [
			'event_uuid' => $uuid,
			'dedupe_key' => $dedupeKey,
			'trigger_key' => $trigger,
			'uid' => $uid,
			'context_app' => $contextApp,
			'created_at' => time(),
		];
		try {
			$qb = $this->db->getQueryBuilder();
			$args = [];
			foreach ($data as $col => $val) {
				$args[$col] = $qb->createNamedParameter($val, is_int($val) ? IQueryBuilder::PARAM_INT : IQueryBuilder::PARAM_STR);
			}
			$qb->insert(self::TABLE)->values($args)->executeStatement();
		} catch (DbException $e) {
			if (!in_array($e->getReason(), [DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION, DbException::REASON_CONSTRAINT_VIOLATION], true)) {
				throw $e;
			}
			$existing = $this->byDedupeKey($dedupeKey);
			if ($existing !== null) {
				return $existing;
			}
			throw $e;
		}
		$data['id'] = (int)$this->db->lastInsertId(self::TABLE);
		$this->scheduleChannels($data);
		return $data;
	}

	public function forUser(string $uid, int $since, int $limit = 120): array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id', 'event_uuid', 'trigger_key', 'uid', 'context_app', 'created_at')
			->from(self::TABLE)
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($since, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'DESC')->setMaxResults($limit)->executeQuery();
		$items = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return array_map($this->cast(...), $items);
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

	private function byDedupeKey(string $key): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('dedupe_key', $qb->createNamedParameter($key)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $this->cast($row);
	}

	private function cast(array $row): array {
		$row['id'] = (int)$row['id'];
		$row['created_at'] = (int)$row['created_at'];
		return $row;
	}

	public function prune(int $before): int {
		$qb = $this->db->getQueryBuilder();
		// Parent records will only be deleted after deliveries are removed by CleanupJob.
		return $qb->delete(self::TABLE)->where($qb->expr()->lt('created_at', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)))->executeStatement();
	}
}
