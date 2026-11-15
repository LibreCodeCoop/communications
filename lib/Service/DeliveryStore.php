<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCA\Communications\Db\DeliveryMapper;
use OCP\IConfig;

/**
 * Durable campaign occurrences and per-tab lease. The server owns audience, schedule,
 * frequency and state transitions; browser-reported impressions are labelled estimates and
 * must not be used for billing without auditing.
 */
class DeliveryStore {
	public const TABLE = DeliveryMapper::TABLE;
	public const CLAIM_TTL = DeliveryMapper::CLAIM_TTL;

	public function __construct(
		private DeliveryMapper $mapper,
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
		$this->recordPageVisit($uid, $app, $newVisit, $pagePath);
		$this->recordImmediatePresentation($uid, $app, $pagePath);
		$this->recordScheduledPresentation($uid, $app);
		$this->prepareDeliveries($uid, $app, $pagePath);
	}

	/** Only record a campaign-scoped event, never the visitor's full path. */
	private function recordPageVisit(string $uid, string $app, bool $newVisit, string $pagePath): void {
		if ($newVisit && $pagePath !== '') {
			foreach ($this->campaigns->activeForTrigger('nextcloud.page.opened') as $campaign) {
				if ($campaign['presentation'] !== 'none'
					&& $this->campaigns->isEligible($campaign, $uid, time())
					&& $this->matchesPlace($campaign, $app, $pagePath)) {
					$this->events->record($uid, 'nextcloud.page.opened', 'campaign_' . $campaign['id']);
				}
			}
		}
	}

	/** Outbound channels are queued on activation; browser views are separate. */
	private function recordImmediatePresentation(string $uid, string $app, string $pagePath): void {
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
	}

	private function recordScheduledPresentation(string $uid, string $app): void {
		foreach ($this->campaigns->activeForTrigger('nextcloud.time.window') as $campaign) {
			if ($campaign['presentation'] !== 'none'
				&& $this->campaigns->isEligible($campaign, $uid, time()) && $this->matchesPlace($campaign, $app)) {
				$key = 'window:' . $campaign['id'] . ':' . $campaign['updated_at'] . ':' . gmdate('Y-m-d');
				$this->events->record($uid, 'nextcloud.time.window', $app, $key);
			}
		}
	}

	/** Build deliveries from the bounded, recipient-scoped event history. */
	private function prepareDeliveries(string $uid, string $app, string $pagePath): void {
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
				if (in_array($trigger, [
					'nextcloud.page.opened',
					'nextcloud.board.published',
					'nextcloud.flow.matched',
					'external.webhook',
				], true) && $event['context_app'] !== 'campaign_' . $campaign['id']) {
					continue;
				}
				$this->mapper->ensureDelivery($event['id'], $campaign['id'], $uid);
			}
		}
	}

	private function sponsoredOptout(string $uid): bool {
		return $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1';
	}

	private function matchesPlace(array $campaign, string $app, string $pagePath = ''): bool {
		$mode = $campaign['app_target_mode'];
		$choices = $campaign['app_target_apps'] ?? [];
		if ($campaign['trigger_key'] === 'nextcloud.page.opened' || $mode === 'area') {
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

	public function pending(string $uid, string $app = 'any', int $limit = 30, string $pagePath = ''): array {
		$rows = $this->mapper->pendingRows($uid);
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
		$id = (int)$campaign['id'];
		$rows = $this->mapper->frequencyRows($uid, $id, $deliveryId);
		$views = [];
		foreach ($rows as $row) {
			if ($row['state'] === 'claimed' && (int)$row['claim_until'] >= $now) {
				return false;
			}
			if ($row['impression_at'] !== null) {
				$views[] = (int)$row['impression_at'];
			}
		}
		return DeliveryFrequencyPolicy::allows($campaign, $views, $now);
	}

	/**
	 * Claim up to five independent banners while keeping modals exclusive.
	 * Each banner retains its own user/campaign lease and claimToken.
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
		if (!$this->mapper->reserveCampaign($uid, (int)$item['campaignId'], (int)$item['deliveryId'], $token, $now)) {
			return null;
		}
		$changed = $this->mapper->claimDelivery((int)$item['deliveryId'], $uid, $token, $now);
		if ($changed) {
			$item['claimToken'] = $token;
			return $item;
		}
		$this->mapper->releaseCampaign($uid, (int)$item['campaignId'], $token);
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

	public function transition(string $uid, int $id, string $token, string $action): bool {
		if (!in_array($action, ['impression', 'dismiss', 'click'], true)) {
			return false;
		}
		$row = $this->mapper->getForUser($id, $uid);
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
		if ($action === 'impression') {
			if (!in_array($row['state'], ['claimed', 'shown'], true)) {
				return false;
			}
			if ($row['impression_at'] !== null) {
				return true;
			}
		} elseif ($action === 'dismiss') {
			if (!in_array($row['state'], ['claimed', 'shown', 'dismissed', 'clicked'], true)) {
				return false;
			}
			if ($row['dismissed_at'] !== null) {
				return true;
			}
		} else {
			if (!in_array($row['state'], ['claimed', 'shown', 'dismissed', 'clicked'], true)) {
				return false;
			}
			if ($row['clicked_at'] !== null) {
				return true;
			}
		}
		$changed = $this->mapper->persistTransition($uid, $id, $token, $action, $now);
		if ($changed && $action !== 'impression') {
			$this->mapper->releaseCampaign($uid, (int)$row['campaign_id'], $token);
		}
		return $changed;
	}

	public function metrics(int $campaignId): array {
		return $this->mapper->metrics($campaignId);
	}

	public function prune(int $before): int {
		return $this->mapper->prune($before);
	}

}
