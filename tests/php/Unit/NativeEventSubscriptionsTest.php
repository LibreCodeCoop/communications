<?php
declare(strict_types=1);
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 *  SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Communications\Tests\Unit;

use OCA\Communications\Service\NativeEventCatalog;
use OCA\Communications\Service\NativeEventSubscriptions;
use PHPUnit\Framework\TestCase;

final class NativeEventSubscriptionsTest extends TestCase {
    private const EVENT = 'OCA\\AnyApplication\\Events\\DocumentCompletedEvent';

    public function testValidEventIsRegisteredForMutationRequests(): void {
        $key = NativeEventCatalog::triggerKey(self::EVENT);
        $json = json_encode([self::EVENT => ['key' => $key]], JSON_THROW_ON_ERROR);
        self::assertSame([self::EVENT], NativeEventSubscriptions::bootstrapClasses($json, 'POST'));
        self::assertSame([], NativeEventSubscriptions::bootstrapClasses($json, 'GET'));
        self::assertSame([], NativeEventSubscriptions::bootstrapClasses($json, 'HEAD'));
        self::assertSame([], NativeEventSubscriptions::bootstrapClasses($json, 'OPTIONS'));
    }

    public function testRejectsMalformedAndForgedSubscriptions(): void {
        $invalid = [
            'invalid JSON' => '{',
            'list' => '["OCA\\\\Any\\\\FooEvent"]',
            'wrong signature' => json_encode([self::EVENT => ['key' => 'php_event.invalid']], JSON_THROW_ON_ERROR),
            'invalid class' => json_encode(['Invalid;Class' => ['key' => 'x']], JSON_THROW_ON_ERROR),
        ];
        foreach ($invalid as $json) {
            self::assertSame([], NativeEventSubscriptions::bootstrapClasses($json, 'POST'));
        }
    }

    public function testMutationDetectionDoesNotAssumeEveryEventIsAWrite(): void {
        self::assertTrue(NativeEventSubscriptions::eventIsMutationByName(self::EVENT));
        self::assertFalse(NativeEventSubscriptions::eventIsMutationByName('OCA\\AnyApplication\\Events\\DocumentViewedEvent'));
    }
}
