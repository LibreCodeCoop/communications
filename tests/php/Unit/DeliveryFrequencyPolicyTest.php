<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\DeliveryFrequencyPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryFrequencyPolicyTest extends TestCase {
	/** @return array<string,array{string,list<int>,int,int,int,bool}> */
	public static function cases(): array {
		return [
			'first occurrence always allowed' => ['once', [], 1, 1, 1, true],
			'once blocks subsequent' => ['once', [1], 0, 0, 0, false],
			'no limits on each event' => ['event', [1, 2], 0, 0, 0, true],
			'total count boundary' => ['event', [1, 2], 2, 0, 0, false],
			'daily rolling window at boundary' => ['daily', [100], 0, 0, 0, false],
			'daily allows at next boundary' => ['daily', [100], 0, 0, 100, true],
			'weekly window still active' => ['weekly', [100], 0, 0, 604799 - 86400, false],
			'custom window still active' => ['custom', [86300], 0, 3, 0, false],
			'custom window passed' => ['custom', [86300], 0, 3, 80, true],
			'per-day limit reached' => ['event', [0, 100], 0, 2, 0, false],
			'per-day limit ignores old entries' => ['event', [-1], 0, 1, 0, true],
		];
	}

	#[DataProvider('cases')]
	public function testDeliveryFrequency(
		string $frequency,
		array $timestamps,
		int $maxTotal,
		int $maxPerDay,
		int $offset,
		bool $expected,
	): void {
		$campaign = [
			'frequency' => $frequency,
			'max_total' => $maxTotal,
			'max_per_day' => $maxPerDay,
			'min_interval_minutes' => 3,
		];
		self::assertSame($expected, DeliveryFrequencyPolicy::allows($campaign, $timestamps, 86400 + $offset));
	}
}
