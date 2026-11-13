<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Durable campaign occurrences and per-tab lease. The server owns audience, schedule, frequency and state transitions; browser-reported impressions are labelled estimates and must not be used for billing without auditing.
 */
class DeliveryStore {
	public const TABLE = 'cc_deliveries';
	public const CLAIM_TTL = 90;

	public function __construct(
		private IDBConnection $db,
		private CampaignRepository $campaigns,
		private EventStore $events,
		private IConfig $config,
		private TriggerCatalog $triggerCatalog,
		private WebRouteCatalog $webRoutes,
	) {
	}

	public function prepare(string $uid, string $app, bool $newVisit, string $pagePath = ''): void {
		if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app) || !$this->triggerCatalog->supportsPlacement($app)) {
			$app = 'any';
		}
		// The browser reports its page, never a URL containing queries, hashes or
		// private file paths. Validate against a navigable app before using it.
		$pagePath = $this->webRoutes->safePagePath($app, $pagePath);
		if ($newVisit && $pagePath !== '') {
			// Page occurrences are scoped to a specific campaign, never broadcast to
			// every app page. Store only the campaign ID, NOT the actual visitor path.
			foreach ($this->campaigns->activeForTrigger('nextcloud.page.opened') as $campaign) {
				if ($campaign['presentation'] !== 'none'
					&& $this->campaigns->isEligible($campaign, $uid, time())
					&& $this->matchesPlace($campaign, $app, $pagePath)) {
					$this->events->record($uid, 'nextcloud.page.opened', 'campaign_' . $campaign['id']);
				}
			}
		}
		// An immediate publication may also use a banner or a modal.
		// Its outbound channels are queued at activation, while the browser
		// presentation becomes eligible on the next visit. Do not produce
		// ephemeral UI occurrences for a channel-only/inbox-only publication.
		foreach ($this->campaigns->activeForTrigger('nextcloud.board.published') as $campaign) {
			if (!in_array($campaign['presentation'], ['banner','modal'], true)
	   || !$this->campaigns->isEligible($campaign, $uid, time())
	   || ($campaign['category'] === 'sponsored' && $this->sponsoredOptout($uid))
	   || !$this->matchesPlace($campaign, $app, $pagePath)) {
				continue;
			}
			$key = 'immediate:' . $campaign['id'] . ':' . $campaign['published_at'] . ':' . gmdate('Y-m-d');
			$this->events->record($uid, 'nextcloud.board.published',
				'campaign_' . $campaign['id'], $key);
		}
		foreach ($this->campaigns->activeForTrigger('nextcloud.time.window') as $campaign) {
			if ($campaign['presentation'] !== 'none'
	   && $this->campaigns->isEligible($campaign, $uid, time()) && $this->matchesPlace($campaign, $app)) {
				$key = 'window:' . $campaign['id'] . ':' . $campaign['updated_at'] . ':' . gmdate('Y-m-d');
				$this->events->record($uid, 'nextcloud.time.window', $app, $key);
			}
		}

		$byTrigger = [];
		// Scan the recent event log in batches. No file paths or document content
		// are stored in the event or returned to the browser.
		foreach ($this->events->forUser($uid, time() - EventStore::RETENTION_SECONDS, 300) as $event) {
			$trigger = $event['trigger_key'];
			if (!isset($byTrigger[$trigger])) {
				$byTrigger[$trigger] = $this->campaigns->activeForTrigger($trigger);
			}
			foreach ($byTrigger[$trigger] as $campaign) {
				if ($campaign['presentation'] === 'none') {
					continue;
				}
				if ($event['created_at'] < max($campaign['created_at'], $campaign['published_at'])) {
					continue;
				}
				if ($campaign['starts_at'] !== null && $event['created_at'] < $campaign['starts_at']) {
					continue;
				}
				if (!$this->campaigns->isEligible($campaign, $uid, time())) {
					continue;
				}
				if ($campaign['category'] === 'sponsored' && $this->sponsoredOptout($uid)) {
					continue;
				}
				if (!$this->matchesPlace($campaign, $app, $pagePath)) {
					continue;
				}
				if (in_array($trigger, ['nextcloud.page.opened','nextcloud.board.published'], true)
					&& $event['context_app'] !== 'campaign_' . $campaign['id']) {
					continue;
				}
				if (in_array($trigger, ['nextcloud.flow.matched','external.webhook'], true)
					&& $event['context_app'] !== 'campaign_' . $campaign['id']) {
					continue;
				}
				$this->ensureDelivery($event['id'], $campaign['id'], $uid);
			}
		}
	}

	private function sponsoredOptout(string $uid): bool {
		return $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1';
	}

	private function matchesPlace(array $campaign, string $app, string $pagePath = ''): bool {
		$mode = $campaign['app_target_mode'] ?? 'legacy';
		$choices = $campaign['app_target_apps'] ?? [];
		if ($campaign['trigger_key'] === 'nextcloud.page.opened' || $mode === 'legacy') {
			if ($campaign['placement'] !== 'any' && $campaign['placement'] !== $app) {
				return false;
			}
		} elseif ($mode === 'only') {
			if ($app === 'any' || !in_array($app, $choices, true)) {
				return false;
			}
		} elseif ($mode === 'exclude') {
			if ($app === 'any' || in_array($app, $choices, true)) {
				return false;
			}
		}
		if ($campaign['trigger_key'] === 'nextcloud.page.opened') {
			return $app !== 'any' && $this->webRoutes->matches(
				$campaign['page_route'] ?? '', $app, $pagePath, $campaign['page_filters'] ?? []
			);
		}
		return true;
	}

	private function ensureDelivery(int $eventId, int $campaignId, string $uid): void {
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

	public function pending(string $uid, string $app = 'any', int $limit = 30, string $pagePath = ''): array {
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
		$now = time();
		$out = [];
		foreach ($rows as $delivery) {
			$campaign = $this->campaigns->find((int)$delivery['campaign_id']);
			if ($campaign === null || $campaign['presentation'] === 'none'
	   || !$this->campaigns->isEligible($campaign, $uid, $now)) {
				continue;
			}
			// A campaign may have been edited after an event was queued. Never
			// deliver an old trigger under a newly selected campaign trigger.
			if ($delivery['trigger_key'] !== $campaign['trigger_key']) {
				continue;
			}
			if ((int)$delivery['event_created_at'] < $campaign['published_at']) {
				continue;
			}
			if ($campaign['category'] === 'sponsored' && $this->sponsoredOptout($uid)) {
				continue;
			}
			if (!$this->matchesPlace($campaign, $app, $pagePath)) {
				continue;
			}
			if ($delivery['state'] === 'claimed' && (int)$delivery['claim_until'] >= $now) {
				continue;
			}
			if (!$this->isWithinFrequency($campaign, $uid, (int)$delivery['id'], $now)) {
				continue;
			}
			$out[] = [
				...$this->presentation($campaign, $uid),
				'deliveryId' => (int)$delivery['id'],
				'eventId' => $delivery['event_uuid'],
			];
		}
		// With equal priority, prefer recent events: a large backlog must never
		// starve a newly triggered campaign outside the bounded response.
		usort($out, static fn (array $a, array $b): int
		 => ($a['manual_order'] <=> $b['manual_order']) ?: ($b['priority'] <=> $a['priority']) ?: ($b['deliveryId'] <=> $a['deliveryId']));
		// Return only the newest eligible occurrence per campaign in this scan.
		// Otherwise hundreds of queued events from one high-priority campaign can
		// fill the response and indefinitely hide other active campaigns.
		$seen = [];
		$result = [];
		foreach ($out as $item) {
			if (isset($seen[$item['campaignId']])) {
				continue;
			}
			$seen[$item['campaignId']] = true;
			$result[] = $item;
			if (count($result) >= $limit) {
				break;
			}
		}
		return $result;
	}

	private function isWithinFrequency(array $campaign, string $uid, int $deliveryId, int $now): bool {
		$frequency = $campaign['frequency'];
		$id = (int)$campaign['id'];
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('impression_at', 'state', 'claim_until')->from(self::TABLE)
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		$views = [];
		foreach ($rows as $row) {
			if ($row['state'] === 'claimed' && (int)$row['claim_until'] >= $now) {
				return false;
			}
			if ($row['impression_at'] !== null) {
				$views[] = (int)$row['impression_at'];
			}
		}
		if ($frequency === 'once' && count($views) > 0) {
			return false;
		}
		if ($campaign['max_total'] > 0 && count($views) >= $campaign['max_total']) {
			return false;
		}
		if ($campaign['max_per_day'] > 0
	  && count(array_filter($views, fn (int $at): bool => $at >= $now - 86400)) >= $campaign['max_per_day']) {
			return false;
		}
		if ($views !== []) {
			$last = max($views);
			$minimum = match ($frequency) {
				'daily' => 86400, 'weekly' => 604800,
				'custom' => $campaign['min_interval_minutes'] * 60,
				default => 0,
			};
			if ($minimum > 0 && $now - $last < $minimum) {
				return false;
			}
		}
		return true;
	}

	private function reserveCampaign(string $uid, int $campaignId, int $deliveryId, string $token, int $now): bool {
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

	private function releaseCampaign(string $uid, int $campaignId, string $token): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update('cc_leases')
			->set('expires_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->executeStatement();
	}

	public function claim(string $uid, string $app, bool $newVisit, string $pagePath = ''): ?array {
		$this->prepare($uid, $app, $newVisit, $pagePath);
		foreach ($this->pending($uid, $app, 30, $pagePath) as $item) {
			$token = bin2hex(random_bytes(24));
			$now = time();
			if (!$this->reserveCampaign($uid, $item['campaignId'], $item['deliveryId'], $token, $now)) {
				continue;
			}
			$qb = $this->db->getQueryBuilder();
			$changed = $qb->update(self::TABLE)
				->set('state', $qb->createNamedParameter('claimed'))
				->set('claim_token', $qb->createNamedParameter($token))
				->set('claim_until', $qb->createNamedParameter($now + self::CLAIM_TTL, IQueryBuilder::PARAM_INT))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($item['deliveryId'], IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('state', $qb->createNamedParameter('pending')),
					$qb->expr()->andX(
						$qb->expr()->eq('state', $qb->createNamedParameter('claimed')),
						$qb->expr()->lt('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
					)
				))
				->executeStatement();
			if ($changed === 1) {
				$item['claimToken'] = $token;
				return $item;
			}
			$this->releaseCampaign($uid, $item['campaignId'], $token);
		}
		return null;
	}

	/**
	 * Claim up to five independent banners while keeping modals exclusive.
	 * Each banner retains its own user/campaign lease and claimToken.
	 * The original claim() contract is retained for older API consumers.
	 */
	public function claimBatch(string $uid, string $app, bool $newVisit, string $pagePath = '', int $limit = 5):array {
		$this->prepare($uid, $app, $newVisit, $pagePath);
		$candidates = $this->pending($uid, $app, 30, $pagePath);
		foreach ($candidates as $candidate) {
			if ($candidate['presentation'] !== 'modal') {
				continue;
			}
			$claimed = $this->reservePendingItem($uid, $candidate);
			if ($claimed !== null) {
				return ['campaign' => $claimed,'campaigns' => []];
			}
		}
		$claimed = [];
		foreach ($candidates as $candidate) {
			if ($candidate['presentation'] !== 'banner') {
				continue;
			}
			$item = $this->reservePendingItem($uid, $candidate);
			if ($item !== null) {
				$claimed[] = $item;
			}
			if (count($claimed) >= max(1, min($limit, 5))) {
				break;
			}
		}
		return ['campaign' => null,'campaigns' => $claimed];
	}

	private function reservePendingItem(string $uid, array $item):?array {
		$token = bin2hex(random_bytes(24));
		$now = time();
		if (!$this->reserveCampaign($uid, (int)$item['campaignId'], (int)$item['deliveryId'], $token, $now)) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$changed = $qb->update(self::TABLE)
			->set('state', $qb->createNamedParameter('claimed'))
			->set('claim_token', $qb->createNamedParameter($token))
			->set('claim_until', $qb->createNamedParameter($now + self::CLAIM_TTL, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$item['deliveryId'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('state', $qb->createNamedParameter('pending')),
				$qb->expr()->andX(
					$qb->expr()->eq('state', $qb->createNamedParameter('claimed')),
					$qb->expr()->lt('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				)
			))->executeStatement();
		if ($changed === 1) {
			$item['claimToken'] = $token;
			return $item;
		}
		$this->releaseCampaign($uid, (int)$item['campaignId'], $token);
		return null;
	}

	public function presentation(array $c, string $uid): array {
		$lang = $this->config->getUserValue($uid, 'core', 'lang', '');
		$c = CampaignRepository::localize($c, $lang);
		return [
			'campaignId' => $c['id'], 'trigger' => $c['trigger_key'],
			'title' => $c['headline'], 'message' => $c['message'],
			'presentation' => $c['presentation'], 'priority' => $c['priority'],
			'manual_order' => $c['manual_order'], 'dismissible' => $c['dismissible'],
			'text_align' => $c['text_align'], 'text_color' => $c['text_color'],
			'ctaLabel' => $c['cta_label'], 'ctaUrl' => $c['cta_url'],
			'category' => $c['category'], 'theme' => $c['theme'],
			'banner_color' => $c['banner_color'], 'banner_icon' => $c['banner_icon'],
		];
	}

	private function getForUser(int $deliveryId, string $uid): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($deliveryId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $row;
	}

	public function transition(string $uid, int $id, string $token, string $action): bool {
		if (!in_array($action, ['impression', 'dismiss', 'click'], true)) {
			return false;
		}
		$row = $this->getForUser($id, $uid);
		if ($row === null || $row['claim_token'] === '' || !hash_equals($row['claim_token'], $token)) {
			return false;
		}
		$now = time();
		// Reading a long modal can take longer than the claim lease. Once it was
		// recorded as shown, the same token may still close/click it; this cannot
		// be reclaimed by another tab because 'shown' is not a pending state.
		if ((int)$row['claim_until'] < $now
	  && !($row['state'] === 'shown' && in_array($action, ['dismiss', 'click'], true))) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('claim_token', $qb->createNamedParameter($token)));
		if ($action === 'impression') {
			if (!in_array($row['state'], ['claimed', 'shown'], true)) {
				return false;
			}
			if ($row['impression_at'] !== null) {
				return true;
			}
			$qb->set('state', $qb->createNamedParameter('shown'))
				->set('impression_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				->andWhere($qb->expr()->eq('state', $qb->createNamedParameter('claimed')));
		} elseif ($action === 'dismiss') {
			if (!in_array($row['state'], ['claimed', 'shown', 'dismissed', 'clicked'], true)) {
				return false;
			}
			if ($row['dismissed_at'] !== null) {
				return true;
			}
			$qb->set('state', $qb->createNamedParameter('dismissed'))
				->set('dismissed_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		} else {
			if (!in_array($row['state'], ['claimed', 'shown', 'dismissed', 'clicked'], true)) {
				return false;
			}
			if ($row['clicked_at'] !== null) {
				return true;
			}
			$qb->set('state', $qb->createNamedParameter('clicked'))
				->set('clicked_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
				->set('dismissed_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT));
		}
		$changed = $qb->executeStatement() === 1;
		if ($changed && $action !== 'impression') {
			$this->releaseCampaign($uid, (int)$row['campaign_id'], $token);
		}
		return $changed;
	}

	/** Legacy API compatibility. Only the user's own queued event can be acknowledged. */
	public function legacyAcknowledge(string $uid, int $campaignId, string $eventUuid): bool {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('d.id', 'd.state')->from(self::TABLE, 'd')
			->innerJoin('d', EventStore::TABLE, 'e', 'e.id = d.event_id')
			->where($qb->expr()->eq('d.uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('d.campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('e.event_uuid', $qb->createNamedParameter($eventUuid)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		if ($row === false) {
			return false;
		}
		if (in_array($row['state'], ['dismissed', 'clicked'], true)) {
			return true;
		}
		if (!in_array($row['state'], ['pending', 'claimed', 'shown'], true)) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('state', $qb->createNamedParameter('dismissed'))
			->set('dismissed_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->executeStatement() === 1;
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
}
