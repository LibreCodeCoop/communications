<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class InboxOrderingPolicy {
	public static function newestFirst(array $campaigns, array $receipts): array {
		usort($campaigns, static fn (array $a, array $b): int => self::compare($a, $b, $receipts));
		return $campaigns;
	}

	private static function compare(array $a, array $b, array $receipts): int {
		$dateA = (int)($a['published_at'] ?: ($receipts[(int)$a['id']] ?? $a['created_at']));
		$dateB = (int)($b['published_at'] ?: ($receipts[(int)$b['id']] ?? $b['created_at']));
		return ($dateB <=> $dateA) ?: ((int)$b['id'] <=> (int)$a['id']);
	}
}
