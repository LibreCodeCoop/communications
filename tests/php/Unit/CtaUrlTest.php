<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\CtaUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CtaUrlTest extends TestCase {
	public static function allowed(): iterable {
		yield 'relative path' => ['/apps/files/'];
		yield 'secure URL' => ['https://example.test/doc?id=5'];
		yield 'plain URL' => ['http://example.test/'];
		yield 'uppercase HTTPS' => ['HTTPS://example.test/doc'];
		yield 'exactly 1900 bytes' => ['https://example.test/' . str_repeat('a', 1879)];
		yield 'email' => ['mailto:help@example.test'];
		yield 'uppercase MAILTO' => ['MAILTO:help@example.test'];
		yield 'phone' => ['tel:+5521999999999'];
		yield 'uppercase TEL' => ['TEL:+5521999999999'];
		yield 'phone without plus' => ['tel:5551234'];
	}

	#[DataProvider('allowed')]
	public function testSafeLinksAreAccepted(string $url): void {
		self::assertTrue(CtaUrl::isAllowed($url));
	}

	public static function rejected(): iterable {
		yield 'empty' => [''];
		yield 'javascript' => ['javascript:alert(1)'];
		yield 'data' => ['data:text/html,hi'];
		yield 'root-relative double slash' => ['//evil.test/path'];
		yield 'encoded credentials' => ['https://user:pass@example.test/'];
		yield 'username without password' => ['https://user@example.test/'];
		yield 'prefixed HTTP' => ['prefixhttps://example.test/'];
		yield 'mailto extra at-sign' => ['mailto:help@example.test@attacker.test'];
		yield 'prefixed MAILTO' => ['prefixmailto:help@example.test'];
		yield 'prefixed TEL' => ['prefixTEL:+5521999999999'];
		yield 'TEL with trailing letters' => ['tel:+5521999999999ext'];
		yield 'backslash' => ['https:\\\\evil.test'];
		yield 'control char' => ["https://example.test/\nfoo"];
		yield 'angle bracket' => ['https://example.test/<script>'];
		yield 'over limit' => ['https://example.test/' . str_repeat('a', 1901)];
		yield '1901-byte boundary' => ['https://example.test/' . str_repeat('a', 1880)];
	}

	#[DataProvider('rejected')]
	public function testUnsafeLinksAreRejected(string $url): void {
		self::assertFalse(CtaUrl::isAllowed($url));
	}
}
