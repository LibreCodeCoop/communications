<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class DeliveryPresentationPolicy {
	private const SCOPED_TRIGGERS = [
		'nextcloud.page.opened',
		'nextcloud.board.published',
		'nextcloud.flow.matched',
		'external.webhook',
	];

	public static function matchesOccurrence(array $campaign, array $event): bool {
		if ($campaign['presentation'] === 'none'
			|| $event['created_at'] < max($campaign['created_at'], $campaign['published_at'])
			|| ($campaign['starts_at'] !== null && $event['created_at'] < $campaign['starts_at'])) {
			return false;
		}

		return !in_array($event['trigger_key'], self::SCOPED_TRIGGERS, true)
			|| $event['context_app'] === 'campaign_' . $campaign['id'];
	}

	public static function pendingIsCurrent(array $campaign, array $delivery, int $now): bool {
		return $campaign['presentation'] !== 'none'
			&& $delivery['trigger_key'] === $campaign['trigger_key']
			&& (int)$delivery['event_created_at'] >= $campaign['published_at']
			&& !($delivery['state'] === 'claimed' && (int)$delivery['claim_until'] >= $now);
	}

	public static function selectNewestPerCampaign(array $presentations, int $limit): array {
		usort($presentations, static fn (array $a, array $b): int => self::compare($a, $b));
		$seen = [];
		$result = [];
		foreach ($presentations as $item) {
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

	private static function compare(array $a, array $b): int {
		return ($a['manual_order'] <=> $b['manual_order'])
			?: ($b['priority'] <=> $a['priority'])
			?: ($b['deliveryId'] <=> $a['deliveryId']);
	}
}
