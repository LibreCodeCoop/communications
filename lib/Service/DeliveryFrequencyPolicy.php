<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/**
 * Shared frequency limits for browser impressions and outbound deliveries.
 * The caller decides which prior states count toward an occurrence.
 */
final class DeliveryFrequencyPolicy {
	/**
	 * @param list<int> $timestamps Prior counted impressions or transmissions.
	 */
	public static function allows(array $campaign, array $timestamps, int $now): bool {
		if ($campaign['max_total'] > 0 && count($timestamps) >= $campaign['max_total']) {
			return false;
		}
		if ($campaign['max_per_day'] > 0
			&& count(array_filter($timestamps, static fn (int $stamp): bool => $stamp >= $now - 86400)) >= $campaign['max_per_day']) {
			return false;
		}
		if ($timestamps === []) {
			return true;
		}
		$minimum = match ($campaign['frequency']) {
			'once' => PHP_INT_MAX,
			'daily' => 86400,
			'weekly' => 604800,
			'custom' => $campaign['min_interval_minutes'] * 60,
			default => 0,
		};
		return $now - max($timestamps) >= $minimum;
	}
}
