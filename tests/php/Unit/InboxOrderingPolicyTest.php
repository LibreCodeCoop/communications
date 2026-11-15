<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\InboxOrderingPolicy;
use PHPUnit\Framework\TestCase;

final class InboxOrderingPolicyTest extends TestCase {
	public function testPublishedDateOutranksDeliveryReceiptAndCreationDate(): void {
		$campaigns = [
			['id' => 1, 'published_at' => 150, 'created_at' => 100],
			['id' => 2, 'published_at' => 0, 'created_at' => 300],
			['id' => 3, 'published_at' => 0, 'created_at' => 180],
		];
		$result = InboxOrderingPolicy::newestFirst($campaigns, [1 => 500, 3 => 200]);
		self::assertSame([2, 3, 1], array_column($result, 'id'));
		self::assertSame([1, 2, 3], array_column($campaigns, 'id'));
	}

	public function testSameTimestampIsSortedByDescendingCampaignId(): void {
		$campaigns = [
			['id' => 2, 'published_at' => 0, 'created_at' => 100],
			['id' => 7, 'published_at' => 500, 'created_at' => 100],
			['id' => 4, 'published_at' => 0, 'created_at' => 100],
		];
		self::assertSame([7, 4, 2], array_column(
			InboxOrderingPolicy::newestFirst($campaigns, [2 => 500, 4 => 500]),
			'id',
		));
	}
}
