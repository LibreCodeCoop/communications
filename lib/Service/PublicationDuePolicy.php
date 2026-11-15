<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class PublicationDuePolicy {
	public static function allows(array $campaign, int $now): bool {
		return $campaign['status'] === 'active'
			&& in_array($campaign['trigger_key'], ['nextcloud.board.published', 'nextcloud.time.window'], true)
			&& ($campaign['board_visible'] || $campaign['notify_users'] || $campaign['notify_activity']
				|| $campaign['notify_email'] || $campaign['gateway_channels'] !== [])
			&& $campaign['published_at'] > 0
			&& ($campaign['starts_at'] === null || $campaign['starts_at'] <= $now)
			&& ($campaign['ends_at'] === null || $campaign['ends_at'] > $now);
	}
}
