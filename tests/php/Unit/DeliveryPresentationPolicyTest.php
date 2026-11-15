<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\DeliveryPresentationPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryPresentationPolicyTest extends TestCase {
	public static function occurrenceCases(): array {
		return [
			'valid scoped webhook' => [[], [], true],
			'event before activation' => [[], ['created_at' => 199], false],
			'event before creation' => [['created_at' => 350], [], false],
			'event before schedule' => [['starts_at' => 350], [], false],
			'wrong scoped campaign' => [[], ['context_app' => 'campaign_11'], false],
			'wrong board publication' => [[], ['trigger_key' => 'nextcloud.board.published', 'context_app' => 'other'], false],
			'unscoped native event' => [[], ['trigger_key' => 'nextcloud.files.created', 'context_app' => 'files'], true],
			'no visual presentation' => [['presentation' => 'none'], [], false],
		];
	}

	#[DataProvider('occurrenceCases')]
	public function testOccurrenceIsBoundToCurrentCampaign(array $campaignChanges, array $eventChanges, bool $expected): void {
		$campaign = array_replace([
			'id' => 10, 'presentation' => 'banner', 'published_at' => 200,
			'created_at' => 100, 'starts_at' => null,
		], $campaignChanges);
		$event = array_replace([
			'trigger_key' => 'external.webhook', 'created_at' => 300, 'context_app' => 'campaign_10',
		], $eventChanges);
		self::assertSame($expected, DeliveryPresentationPolicy::matchesOccurrence($campaign, $event));
	}

	public static function pendingCases(): array {
		return [
			'pending current event' => [[], [], 500, true],
			'updated campaign trigger' => [[], ['trigger_key' => 'other'], 500, false],
			'event from prior publication' => [['published_at' => 200], ['event_created_at' => 199], 500, false],
			'claimed until now is unavailable' => [[], ['state' => 'claimed', 'claim_until' => 500], 500, false],
			'expired claim may be retried' => [[], ['state' => 'claimed', 'claim_until' => 499], 500, true],
			'nonvisual campaign' => [['presentation' => 'none'], [], 500, false],
		];
	}

	#[DataProvider('pendingCases')]
	public function testPendingOccurrenceIsStillCurrent(array $campaignChanges, array $deliveryChanges, int $now, bool $expected): void {
		$campaign = array_replace(['presentation' => 'banner', 'trigger_key' => 'test', 'published_at' => 100], $campaignChanges);
		$delivery = array_replace([
			'trigger_key' => 'test', 'event_created_at' => 200, 'state' => 'pending', 'claim_until' => 0,
		], $deliveryChanges);
		self::assertSame($expected, DeliveryPresentationPolicy::pendingIsCurrent($campaign, $delivery, $now));
	}

	public function testLimitAndFairnessSelectLatestEventFromDistinctCampaigns(): void {
		$items = [
			['campaignId' => 1, 'manual_order' => 0, 'priority' => 10, 'deliveryId' => 12],
			['campaignId' => 2, 'manual_order' => 0, 'priority' => 10, 'deliveryId' => 20],
			['campaignId' => 1, 'manual_order' => 0, 'priority' => 10, 'deliveryId' => 13],
			['campaignId' => 3, 'manual_order' => 0, 'priority' => 2, 'deliveryId' => 30],
		];
		self::assertSame([20, 13], array_column(DeliveryPresentationPolicy::selectNewestPerCampaign($items, 2), 'deliveryId'));
		self::assertSame([20, 13, 30], array_column(DeliveryPresentationPolicy::selectNewestPerCampaign($items, 3), 'deliveryId'));
		self::assertCount(4, $items);
	}
}
