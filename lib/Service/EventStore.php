<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCA\Communications\BackgroundJob\EventChannelDeliveryJob;
use OCA\Communications\Db\EventMapper;
use OCP\BackgroundJob\IJobList;

class EventStore {
	public const TABLE = EventMapper::TABLE;
	public const RETENTION_SECONDS = 60 * 60 * 24 * 30;

	public function __construct(
		private EventMapper $mapper,
		private IJobList $jobs,
		private CampaignRepository $campaigns,
	) {
	}

	private function scheduleChannels(array $event): void {
		if (in_array($event['trigger_key'], ['nextcloud.board.published', 'nextcloud.time.window'], true)) {
			return;
		}
		foreach ($this->campaigns->activeForTrigger($event['trigger_key']) as $campaign) {
			if ($campaign['board_visible'] || $campaign['notify_users'] || $campaign['notify_activity']
				|| $campaign['notify_email'] || $campaign['gateway_channels'] !== []) {
				$this->jobs->add(EventChannelDeliveryJob::class, ['eventId' => (int)$event['id']]);
				return;
			}
		}
	}

	public function byId(int $id): ?array {
		return $this->mapper->byId($id);
	}

	/**
	 * @return array{id:int,event_uuid:string,uid:string,trigger_key:string,context_app:string,created_at:int}
	 */
	public function record(string $uid, string $trigger, string $contextApp = 'any', ?string $idempotencyKey = null): array {
		EventInputPolicy::assertValid($uid, $trigger, $contextApp);
		$dedupeKey = $idempotencyKey === null
			? bin2hex(random_bytes(32))
			: hash('sha256', $uid . "\0" . $trigger . "\0" . $idempotencyKey);
		$existing = $this->mapper->byDedupeKey($dedupeKey);
		if ($existing !== null) {
			$this->scheduleChannels($existing);
			return $existing;
		}
		$hex = bin2hex(random_bytes(16));
		$data = [
			'event_uuid' => substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
				. substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12),
			'dedupe_key' => $dedupeKey,
			'trigger_key' => $trigger,
			'uid' => $uid,
			'context_app' => $contextApp,
			'created_at' => time(),
		];
		$result = $this->mapper->persist($data);
		if ($result['inserted']) {
			$this->scheduleChannels($result['event']);
		}
		return $result['event'];
	}

	public function forUser(string $uid, int $since, int $limit = 120): array {
		return $this->mapper->forUser($uid, $since, $limit);
	}

	public function byIdForUser(int $id, string $uid): ?array {
		return $this->mapper->byIdForUser($id, $uid);
	}

	public function prune(int $before): int {
		return $this->mapper->prune($before);
	}
}
