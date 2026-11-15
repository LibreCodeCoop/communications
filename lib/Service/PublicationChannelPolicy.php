<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/** Pure rules shared by timed broadcasts and subject-scoped event deliveries. */
final class PublicationChannelPolicy {
	/**
	 * @param list<string> $consentedGateways Already validated and opted-in gateways.
	 * @return list<string>
	 */
	public static function channels(array $campaign, string $uid, array $consentedGateways, bool $event): array {
		$creator = $uid === $campaign['created_by'];
		$channels = [];
		if ($event && $campaign['board_visible']) {
			$channels[] = 'inbox';
		}
		if ($campaign['notify_users'] && !$creator) {
			$channels[] = 'notifications';
		}
		if ($campaign['notify_activity']) {
			$channels[] = 'activity';
		}
		if ($campaign['notify_email'] && !$creator) {
			$channels[] = 'email';
		}
		foreach ($consentedGateways as $gateway) {
			$channels[] = 'gateway:' . $gateway;
		}
		return $channels;
	}

	/** Revalidate the recorded event's timestamp and explicit context. */
	public static function matchesEvent(array $campaign, array $event): bool {
		$id = (int)$campaign['id'];
		$version = (int)$campaign['published_at'];
		$created = (int)$event['created_at'];
		if ($version <= 0 || $created < max((int)$campaign['created_at'], $version)
			|| ($campaign['starts_at'] !== null && $created < (int)$campaign['starts_at'])
			|| ($campaign['ends_at'] !== null && $created >= (int)$campaign['ends_at'])) {
			return false;
		}
		return !in_array($event['trigger_key'], ['external.webhook','nextcloud.flow.matched','nextcloud.page.opened'], true)
			|| $event['context_app'] === 'campaign_' . $id;
	}
}
