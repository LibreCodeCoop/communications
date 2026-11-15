<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\NativeEventCatalog;
use OCA\Communications\Service\NativeEventClassPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NativeEventClassPolicyTest extends TestCase {
	public static function files(): array {
		return [
			'valid app event' => ['DocumentSignedEvent.php', 'OCA\\Signer\\Events\\DocumentSignedEvent'],
			'nested event class' => ['Document/DocumentSignedEvent.php', 'OCA\\Signer\\Events\\Document\\DocumentSignedEvent'],
			'event missing suffix' => ['Something.php', null],
			'path traversal' => ['../DocumentSignedEvent.php', null],
			'rooted path' => ['/DocumentSignedEvent.php', null],
			'other separator' => ['Document\\OtherEvent.php', null],
			'malformed class name' => ['Doc;Event.php', null],
			'too deep' => ['A/B/C/D/E/F/G/DocumentSignedEvent.php', null],
		];
	}

	#[DataProvider('files')]
	public function testOnlyWellFormedEventClassNamesCanBeDerived(string $path, ?string $expected): void {
		self::assertSame($expected, NativeEventClassPolicy::fromRelativePath($path, 'OCA\\Signer\\Events'));
	}

	public function testEventKeyIsStableAndCanonicalized(): void {
		self::assertSame(
			NativeEventCatalog::triggerKey('OCA\\Signer\\Events\\DocumentSignedEvent'),
			NativeEventCatalog::triggerKey('\\OCA\\Signer\\Events\\DocumentSignedEvent'),
		);
	}
}
