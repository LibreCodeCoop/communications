<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\WebhookSignatureVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureVerifierTest extends TestCase {
	public function testAcceptsValidTimestampAndRejectsTampering(): void {
		$verifier = new WebhookSignatureVerifier();
		$body = '{"uid":"example"}';
		$signature = 'sha256=' . hash_hmac('sha256', '1000.' . $body, 'test-secret');
		self::assertTrue($verifier->valid('test-secret', $body, '1000', $signature, 1000));
		self::assertTrue($verifier->valid('test-secret', $body, '1000', $signature, 1300));
		self::assertFalse($verifier->valid('test-secret', $body, '1000', $signature, 1301));
		self::assertFalse($verifier->valid('test-secret', '{"uid":"other"}', '1000', $signature, 1000));
		self::assertFalse($verifier->valid('wrong-secret', $body, '1000', $signature, 1000));
		self::assertFalse($verifier->valid('test-secret', $body, '-1000', $signature, 1000));
		self::assertFalse($verifier->valid('test-secret', $body, '1000', strtoupper($signature), 1000));
	}
}
