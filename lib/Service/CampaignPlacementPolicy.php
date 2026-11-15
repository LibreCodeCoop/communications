<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class CampaignPlacementPolicy {
	public static function matchesApp(array $campaign, string $app): bool {
		$mode = $campaign['app_target_mode'];
		if ($campaign['trigger_key'] === 'nextcloud.page.opened' || $mode === 'area') {
			return $campaign['placement'] === 'any' || $campaign['placement'] === $app;
		}

		$choices = $campaign['app_target_apps'] ?? [];
		return match ($mode) {
			'only' => $app !== 'any' && in_array($app, $choices, true),
			'exclude' => $app !== 'any' && !in_array($app, $choices, true),
			'all' => true,
			default => false,
		};
	}
}
