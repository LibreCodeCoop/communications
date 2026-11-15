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
		$pagePath = $this->webRoutes->safePagePath($app, $pagePath);
		$this->recordPageVisit($uid, $app, $newVisit, $pagePath);
		$this->recordImmediatePresentation($uid, $app, $pagePath);
		$this->recordScheduledPresentation($uid, $app);
		$this->prepareDeliveries($uid, $app, $pagePath);
	}

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

	private function prepareDeliveries(string $uid, string $app, string $pagePath): void {
		$byTrigger = [];
		foreach ($this->events->forUser($uid, time() - EventStore::RETENTION_SECONDS, 300) as $event) {
			$trigger = $event['trigger_key'];
			if (!isset($byTrigger[$trigger])) {
				$byTrigger[$trigger] = $this->campaigns->activeForTrigger($trigger);
			}
			foreach ($byTrigger[$trigger] as $campaign) {
				if (!DeliveryPresentationPolicy::matchesOccurrence($campaign, $event)) {
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
				$this->mapper->ensureDelivery($event['id'], $campaign['id'], $uid);
			}
		}
	}

	private function sponsoredOptout(string $uid): bool {
		return $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1';
	}

	private function matchesPlace(array $campaign, string $app, string $pagePath = ''): bool {
		if (!CampaignPlacementPolicy::matchesApp($campaign, $app)) {
			return false;
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
			if ($campaign === null
				|| !DeliveryPresentationPolicy::pendingIsCurrent($campaign, $delivery, $now)
				|| !$this->campaigns->isEligible($campaign, $uid, $now)) {
				continue;
			}
			if ($campaign['category'] === 'sponsored' && $this->sponsoredOptout($uid)) {
				continue;
			}
			if (!$this->matchesPlace($campaign, $app, $pagePath)) {
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
		return DeliveryPresentationPolicy::selectNewestPerCampaign($out, $limit);
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

	/** Reserve an exclusive modal or up to five independently leased banners. */
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
		$decision = DeliveryTransitionPolicy::decision($row, $action, $now);
		if ($decision === DeliveryTransitionPolicy::REJECT) {
			return false;
		}
		if ($decision === DeliveryTransitionPolicy::ALREADY_APPLIED) {
			return true;
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
