<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\BackgroundJob\EventChannelDeliveryJob;
use OCA\Communications\Db\EventMapper;
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\EventStore;
use OCP\BackgroundJob\IJobList;
use PHPUnit\Framework\TestCase;

final class EventStoreTest extends TestCase {
	public function testDuplicateEventSchedulesARecoverableJob(): void {
		$existing = [
			'id' => 8, 'event_uuid' => 'uuid', 'uid' => 'alice',
			'trigger_key' => 'external.webhook', 'context_app' => 'campaign_12',
			'created_at' => 100,
		];
		$mapper = $this->createMock(EventMapper::class);
		$mapper->method('byDedupeKey')->willReturn($existing);
		$mapper->expects(self::never())->method('persist');
		$campaigns = $this->createMock(CampaignRepository::class);
		$campaigns->method('activeForTrigger')->with('external.webhook')
			->willReturn([[
				'board_visible' => false,
				'notify_users' => true,
				'notify_activity' => false,
				'notify_email' => false,
				'gateway_channels' => [],
			]]);
		$jobs = $this->createMock(IJobList::class);
		$jobs->expects(self::once())->method('add')
			->with(EventChannelDeliveryJob::class, ['eventId' => 8]);
		$service = new EventStore($mapper, $jobs, $campaigns);
		self::assertSame($existing, $service->record('alice', 'external.webhook', 'campaign_12', 'external-id'));
	}

	public function testInvalidContextCannotReachMapper(): void {
		$mapper = $this->createMock(EventMapper::class);
		$mapper->expects(self::never())->method('persist');
		$service = new EventStore(
			$mapper,
			$this->createMock(IJobList::class),
			$this->createMock(CampaignRepository::class),
		);
		$this->expectException(\InvalidArgumentException::class);
		$service->record('alice', 'external.webhook', 'unexpected/path', 'external-id');
	}
}
