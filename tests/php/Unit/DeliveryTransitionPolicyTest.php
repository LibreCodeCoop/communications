<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\DeliveryTransitionPolicy as Policy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeliveryTransitionPolicyTest extends TestCase {
	public static function decisions(): array {
		return [
			'first impression' => ['impression', [], Policy::PERSIST],
			'duplicate impression' => ['impression', ['impression_at' => 20], Policy::ALREADY_APPLIED],
			'dismiss active modal' => ['dismiss', [], Policy::PERSIST],
			'duplicate dismissal' => ['dismiss', ['dismissed_at' => 20], Policy::ALREADY_APPLIED],
			'click already dismissed' => ['click', ['state' => 'dismissed'], Policy::PERSIST],
			'duplicate click' => ['click', ['state' => 'clicked', 'clicked_at' => 20], Policy::ALREADY_APPLIED],
			'expired claimed dismissal' => ['dismiss', ['claim_until' => 99], Policy::REJECT],
			'expired shown dismissal' => ['dismiss', ['claim_until' => 99, 'state' => 'shown'], Policy::PERSIST],
			'expired shown click' => ['click', ['claim_until' => 99, 'state' => 'shown'], Policy::PERSIST],
			'expired shown impression' => ['impression', ['claim_until' => 99, 'state' => 'shown'], Policy::REJECT],
			'pending cannot acknowledge' => ['dismiss', ['state' => 'pending'], Policy::REJECT],
			'click is unknown for pending' => ['click', ['state' => 'pending'], Policy::REJECT],
			'unknown action' => ['delete', [], Policy::REJECT],
		];
	}

	#[DataProvider('decisions')]
	public function testStateTransitionDecision(string $action, array $changes, string $expected): void {
		$row = array_replace([
			'state' => 'claimed', 'claim_until' => 100, 'impression_at' => null,
			'dismissed_at' => null, 'clicked_at' => null,
		], $changes);
		self::assertSame($expected, Policy::decision($row, $action, 100));
	}
}
