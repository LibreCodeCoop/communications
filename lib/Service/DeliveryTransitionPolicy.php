<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class DeliveryTransitionPolicy {
	public const REJECT = 'reject';
	public const ALREADY_APPLIED = 'already_applied';
	public const PERSIST = 'persist';

	public static function decision(array $row, string $action, int $now): string {
		if (!in_array($action, ['impression', 'dismiss', 'click'], true)) {
			return self::REJECT;
		}

		if ((int)$row['claim_until'] < $now
			&& !($row['state'] === 'shown' && in_array($action, ['dismiss', 'click'], true))) {
			return self::REJECT;
		}

		if ($action === 'impression') {
			if (!in_array($row['state'], ['claimed', 'shown'], true)) {
				return self::REJECT;
			}
			return $row['impression_at'] !== null ? self::ALREADY_APPLIED : self::PERSIST;
		}

		if (!in_array($row['state'], ['claimed', 'shown', 'dismissed', 'clicked'], true)) {
			return self::REJECT;
		}

		$field = $action === 'dismiss' ? 'dismissed_at' : 'clicked_at';
		return $row[$field] !== null ? self::ALREADY_APPLIED : self::PERSIST;
	}
}
