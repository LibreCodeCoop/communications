<?php
declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 *  SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\CtaUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CtaUrlTest extends TestCase {
    public static function allowed(): iterable {
        yield 'relative path' => ['/apps/files/'];
        yield 'secure URL' => ['https://example.test/doc?id=5'];
        yield 'plain URL' => ['http://example.test/'];
        yield 'email' => ['mailto:help@example.test'];
        yield 'phone' => ['tel:+5521999999999'];
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
        yield 'backslash' => ["https:\\\\evil.test"];
        yield 'control char' => ["https://example.test/\nfoo"];
        yield 'angle bracket' => ['https://example.test/<script>'];
        yield 'over limit' => ['https://example.test/' . str_repeat('a', 1901)];
    }

    #[DataProvider('rejected')]
    public function testUnsafeLinksAreRejected(string $url): void {
        self::assertFalse(CtaUrl::isAllowed($url));
    }
}
