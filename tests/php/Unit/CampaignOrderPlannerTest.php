<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use InvalidArgumentException;
use OCA\Communications\Service\CampaignOrderPlanner;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CampaignOrderPlannerTest extends TestCase {
	private function planner(): CampaignOrderPlanner {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $message): string => $message);
		return new CampaignOrderPlanner($l10n);
	}

	private static function campaigns(): array {
		return [
			['id' => 1, 'status' => 'active', 'manual_order' => 10, 'priority' => 10],
			['id' => 2, 'status' => 'active', 'manual_order' => 20, 'priority' => 10],
			['id' => 3, 'status' => 'draft', 'manual_order' => 30, 'priority' => 10],
			['id' => 4, 'status' => 'archived', 'manual_order' => 15, 'priority' => 10],
		];
	}

	/** @return array<string,array{int,string,?int,?array}> */
	public static function validMoves(): array {
		return [
			'up' => [2, 'up', null, [2, 1, 3]],
			'down' => [2, 'down', null, [1, 3, 2]],
			'before' => [3, 'before', 1, [3, 1, 2]],
			'after' => [1, 'after', 3, [2, 3, 1]],
			'first cannot move up' => [1, 'up', null, [1, 2, 3]],
			'last cannot move down' => [3, 'down', null, [1, 2, 3]],
			'same target is unchanged' => [2, 'before', 2, [1, 2, 3]],
			'nonexistent campaign' => [99, 'up', null, null],
			'archived campaign' => [4, 'down', null, null],
		];
	}

	#[DataProvider('validMoves')]
	public function testPlansOrdering(int $id, string $direction, ?int $targetId, ?array $expected): void {
		$plan = $this->planner()->plan(self::campaigns(), $id, $direction, $targetId);
		self::assertSame($expected, $plan === null ? null : array_column($plan, 'id'));
	}

	/** @return array<string,array{string,?int,string}> */
	public static function invalidMoves(): array {
		return [
			'unknown direction' => ['sideways', null, 'Invalid reordering operation.'],
			'missing target' => ['after', null, 'Select the destination campaign.'],
			'zero target' => ['before', 0, 'Select the destination campaign.'],
			'unknown target' => ['after', 99, 'Destination campaign unavailable.'],
			'archived target' => ['before', 4, 'Destination campaign unavailable.'],
		];
	}

	#[DataProvider('invalidMoves')]
	public function testRejectsInvalidMove(string $direction, ?int $targetId, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->planner()->plan(self::campaigns(), 2, $direction, $targetId);
	}

	public function testTieSortingUsesPriorityAndId(): void {
		$rows = [
			['id' => 1, 'status' => 'active', 'manual_order' => 10, 'priority' => 5],
			['id' => 2, 'status' => 'active', 'manual_order' => 10, 'priority' => 10],
			['id' => 3, 'status' => 'active', 'manual_order' => 10, 'priority' => 10],
		];
		$plan = $this->planner()->plan($rows, 1, 'up');
		self::assertSame([3, 1, 2], array_column($plan, 'id'));
	}
}
