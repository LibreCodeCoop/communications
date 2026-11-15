<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\EventInputPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EventInputPolicyTest extends TestCase {
	/** @return array<string,array{string,string,string,string}> */
	public static function invalidSubjects(): array {
		return [
			'empty user' => ['', 'external.webhook', 'any', 'Invalid event subject.'],
			'user over 64 chars' => [str_repeat('u', 65), 'external.webhook', 'any', 'Invalid event subject.'],
			'empty trigger' => ['alice', '', 'any', 'Invalid event subject.'],
			'trigger over 64 chars' => ['alice', str_repeat('x', 65), 'any', 'Invalid event subject.'],
			'path-like app context' => ['alice', 'external.webhook', 'files/private', 'Invalid app context.'],
			'oversized app context' => ['alice', 'external.webhook', str_repeat('x', 33), 'Invalid app context.'],
		];
	}

	#[DataProvider('invalidSubjects')]
	public function testRejectsInvalidEventIdentity(string $uid, string $trigger, string $context, string $message): void {
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		EventInputPolicy::assertValid($uid, $trigger, $context);
	}

	public function testAcceptsCampaignScopedEvent(): void {
		$this->expectNotToPerformAssertions();
		EventInputPolicy::assertValid('alice', 'external.webhook', 'campaign_42');
	}
}
