<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\CampaignPlacementPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CampaignPlacementPolicyTest extends TestCase {
	public static function locations(): array {
		return [
			'area matches selected app' => ['area', 'files', ['files'], 'files', 'nextcloud.files.created', true],
			'area rejects another app' => ['area', 'files', [], 'dashboard', 'nextcloud.files.created', false],
			'any area accepts' => ['area', 'any', [], 'dashboard', 'nextcloud.files.created', true],
			'all apps accepts' => ['all', 'files', [], 'dashboard', 'nextcloud.files.created', true],
			'allowlist matches' => ['only', 'any', ['files'], 'files', 'nextcloud.files.created', true],
			'allowlist rejects unknown page' => ['only', 'any', ['files'], 'any', 'nextcloud.files.created', false],
			'exclude blocks chosen' => ['exclude', 'any', ['files'], 'files', 'nextcloud.files.created', false],
			'exclude permits another app' => ['exclude', 'any', ['files'], 'dashboard', 'nextcloud.files.created', true],
			'exclude rejects unknown page' => ['exclude', 'any', ['files'], 'any', 'nextcloud.files.created', false],
			'page event respects placement' => ['all', 'files', [], 'dashboard', 'nextcloud.page.opened', false],
			'unknown mode fails closed' => ['unexpected', 'any', [], 'files', 'nextcloud.files.created', false],
		];
	}

	#[DataProvider('locations')]
	public function testApplicationTargeting(
		string $mode, string $placement, array $choices, string $app, string $trigger, bool $expected,
	): void {
		$campaign = [
			'app_target_mode' => $mode, 'placement' => $placement,
			'app_target_apps' => $choices, 'trigger_key' => $trigger,
		];
		self::assertSame($expected, CampaignPlacementPolicy::matchesApp($campaign, $app));
	}
}
