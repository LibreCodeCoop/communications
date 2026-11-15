<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\GuestBannerPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GuestBannerPolicyTest extends TestCase {
	/** @return array<string,array{array,bool}> */
	public static function cases(): array {
		return [
			'approved public banner' => [[], true],
			'group audience denied' => [['audience_type' => 'group'], false],
			'private modal denied' => [['presentation' => 'modal'], false],
			'unscheduled event denied' => [['trigger_key' => 'nextcloud.user.login'], false],
			'sponsored denied' => [['category' => 'sponsored'], false],
			'application restricted denied' => [['placement' => 'files'], false],
			'app allowlist denied' => [['app_target_mode' => 'only'], false],
			'Inbox copy denied' => [['board_visible' => true], false],
			'area unrestricted allowed' => [['app_target_mode' => 'area'], true],
			'all applications allowed' => [['app_target_mode' => 'all'], true],
			'unsupported application mode denied' => [['app_target_mode' => 'unknown'], false],
		];
	}

	#[DataProvider('cases')]
	public function testPublicEligibility(array $changes, bool $expected): void {
		$campaign = array_replace([
			'audience_type' => 'all',
			'presentation' => 'banner',
			'trigger_key' => 'nextcloud.time.window',
			'category' => 'institutional',
			'placement' => 'any',
			'app_target_mode' => 'area',
			'board_visible' => false,
		], $changes);
		self::assertSame($expected, GuestBannerPolicy::supports($campaign));
	}
}
