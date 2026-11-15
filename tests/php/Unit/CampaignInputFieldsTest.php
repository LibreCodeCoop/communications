<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use InvalidArgumentException;
use OCA\Communications\Service\CampaignInputFields;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

if (!interface_exists(IL10N::class)) {
	require_once __DIR__ . '/../../../vendor/nextcloud/ocp/OCP/IL10N.php';
}

final class CampaignInputFieldsTest extends TestCase {
	private function fields(array $values): CampaignInputFields {
		$l10n = $this->createStub(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $value): string => $value);
		return new CampaignInputFields($values, $l10n);
	}

	public function testTrimsTextAndKeepsOptionalFieldsEmpty(): void {
		$fields = $this->fields(['headline' => '  Launch  ']);
		self::assertSame('Launch', $fields->text('headline', 20, true));
		self::assertSame('', $fields->text('description', 100));
	}

	public static function invalidText(): array {
		return [
			'required empty text' => [['headline' => '  '], 'Review the field headline'],
			'text exceeds limit' => [['headline' => '123456'], 'Review the field headline'],
			'array is not text' => [['headline' => []], 'Invalid field: headline'],
		];
	}

	#[DataProvider('invalidText')]
	public function testRejectsInvalidText(array $input, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->fields($input)->text('headline', 5, true);
	}

	public function testNumericBoundsPreserveZeroAndMaximum(): void {
		self::assertSame(0, $this->fields([])->number('max_per_day', 10));
		self::assertSame(10, $this->fields(['max_per_day' => '10'])->number('max_per_day', 10));
	}

	public static function invalidNumbers(): array {
		return [
			'negative' => [-1],
			'above maximum' => [11],
			'fraction' => ['1.3'],
			'not numeric' => ['invalid'],
		];
	}

	#[DataProvider('invalidNumbers')]
	public function testRejectsInvalidNumbers(mixed $value): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid number: max_per_day');
		$this->fields(['max_per_day' => $value])->number('max_per_day', 10);
	}

	public function testOptionalAndZeroTimestamps(): void {
		self::assertNull($this->fields([])->timestamp('starts_at'));
		self::assertNull($this->fields(['starts_at' => ''])->timestamp('starts_at'));
		self::assertSame(0, $this->fields(['starts_at' => 0])->timestamp('starts_at'));
		self::assertSame(120, $this->fields(['starts_at' => '120'])->timestamp('starts_at'));
	}

	public static function invalidDates(): array {
		return [
			'negative timestamp' => [-1],
			'fraction' => ['10.5'],
			'invalid string' => ['tonight'],
		];
	}

	#[DataProvider('invalidDates')]
	public function testRejectsInvalidTimestamps(mixed $value): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Invalid date: starts_at');
		$this->fields(['starts_at' => $value])->timestamp('starts_at');
	}
}
