<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\PublicationChannelPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicationChannelPolicyTest extends TestCase {
	/** @return array<string,array{string,bool,array<string,mixed>,list<string>}> */
	public static function deliveryCases(): array {
		return [
			'broadcast for recipient' => ['reader', false, [], ['notifications','activity','email','gateway:sms']],
			'broadcast for author' => ['author', false, [], ['activity','gateway:sms']],
			'event for recipient' => ['reader', true, [], ['inbox','notifications','activity','email','gateway:sms']],
			'event for author' => ['author', true, [], ['inbox','activity','gateway:sms']],
			'no enabled channels' => ['reader', false, [
				'notify_users' => false,'notify_activity' => false,'notify_email' => false,
			], ['gateway:sms']],
			'no consent for gateway' => ['reader', false, ['consented' => []], ['notifications','activity','email']],
			'inbox is only for event' => ['reader', true, [
				'notify_users' => false,'notify_activity' => false,'notify_email' => false,'consented' => [],
			], ['inbox']],
		];
	}

	#[DataProvider('deliveryCases')]
	public function testChannelSelection(string $uid, bool $event, array $changes, array $expected): void {
		$campaign = array_replace([
			'created_by' => 'author','board_visible' => true,
			'notify_users' => true,'notify_activity' => true,'notify_email' => true,
		], $changes);
		$consented = $campaign['consented'] ?? ['sms'];
		unset($campaign['consented']);
		self::assertSame($expected, PublicationChannelPolicy::channels($campaign, $uid, $consented, $event));
	}

	/** @return array<string,array{array<string,mixed>,array<string,mixed>,bool}> */
	public static function eventCases(): array {
		return [
			'valid scoped webhook' => [[], [], true],
			'wrong campaign context' => [[], ['context_app' => 'campaign_22'], false],
			'webhook without context' => [[], ['context_app' => ''], false],
			'unscoped native trigger' => [[], ['trigger_key' => 'nextcloud.user.login','context_app' => ''], true],
			'not yet published' => [['published_at' => 0], [], false],
			'event before publication' => [[], ['created_at' => 109], false],
			'event before campaign creation' => [['created_at' => 120], ['created_at' => 119], false],
			'event before scheduled start' => [['starts_at' => 130], ['created_at' => 129], false],
			'event on scheduled start' => [['starts_at' => 130], ['created_at' => 130], true],
			'event on expiration boundary' => [['ends_at' => 150], ['created_at' => 150], false],
			'event before expiration' => [['ends_at' => 150], ['created_at' => 149], true],
		];
	}

	#[DataProvider('eventCases')]
	public function testRecordedEventMatchesCampaign(array $campaignChanges, array $eventChanges, bool $expected): void {
		$campaign = array_replace([
			'id' => 21,'published_at' => 110,'created_at' => 100,
			'starts_at' => null,'ends_at' => null,
		], $campaignChanges);
		$event = array_replace([
			'created_at' => 140,'trigger_key' => 'external.webhook','context_app' => 'campaign_21',
		], $eventChanges);
		self::assertSame($expected, PublicationChannelPolicy::matchesEvent($campaign, $event));
	}
}
