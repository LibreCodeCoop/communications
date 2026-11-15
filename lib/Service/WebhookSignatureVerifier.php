<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class WebhookSignatureVerifier {
	public function valid(string $secret, string $raw, string $timestamp, string $signature, int $now): bool {
		if (!ctype_digit($timestamp) || abs($now - (int)$timestamp) > 300
			|| preg_match('/^sha256=[a-f0-9]{64}$/', $signature) !== 1) {
			return false;
		}
		$expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
		return hash_equals($expected, $signature);
	}
}
