<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\PublicationDuePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationDuePolicyTest extends TestCase {
	/** @return array<string,array{array<string,mixed>,int,bool}> */
	public static function scenarios(): array {
		return [
			'active publication' => [[], 100, true],
			'inactive draft' => [['status' => 'draft'], 100, false],
			'event publication' => [['trigger_key' => 'external.webhook'], 100, false],
			'no channel' => [['notify_email' => false], 100, false],
			'not published' => [['published_at' => 0], 100, false],
			'future start' => [['starts_at' => 101], 100, false],
			'starts now' => [['starts_at' => 100], 100, true],
			'expires now' => [['ends_at' => 100], 100, false],
			'just before expiration' => [['ends_at' => 101], 100, true],
			'Inbox only' => [['notify_email' => false, 'board_visible' => true], 100, true],
		];
	}

	#[DataProvider('scenarios')]
	public function testDuePublicationRules(array $changes, int $now, bool $expected): void {
		$campaign = array_replace([
			'status' => 'active',
			'trigger_key' => 'nextcloud.time.window',
			'board_visible' => false,
			'notify_users' => false,
			'notify_activity' => false,
			'notify_email' => true,
			'gateway_channels' => [],
			'published_at' => 10,
			'starts_at' => null,
			'ends_at' => null,
		], $changes);
		self::assertSame($expected, PublicationDuePolicy::allows($campaign, $now));
	}
}
