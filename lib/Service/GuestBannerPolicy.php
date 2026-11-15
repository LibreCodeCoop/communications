<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/**
 * Eligibility policy shared by public publication writes and read projection.
 */
final class GuestBannerPolicy {
	public static function supports(array $campaign): bool {
		return $campaign['audience_type'] === 'all'
			&& $campaign['presentation'] === 'banner'
			&& $campaign['trigger_key'] === 'nextcloud.time.window'
			&& $campaign['category'] === 'institutional'
			&& $campaign['placement'] === 'any'
			&& in_array($campaign['app_target_mode'], ['legacy', 'all'], true)
			&& !$campaign['board_visible'];
	}
}
