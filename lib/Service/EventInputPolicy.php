<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/** Validate the event identity before any persistence or scheduling. */
final class EventInputPolicy {
	public static function assertValid(string $uid, string $trigger, string $contextApp): void {
		if ($uid === '' || mb_strlen($uid) > 64 || $trigger === '' || mb_strlen($trigger) > 64) {
			throw new \InvalidArgumentException('Invalid event subject.');
		}
		if (!preg_match('/^[a-z0-9_-]{1,32}$/', $contextApp)) {
			throw new \InvalidArgumentException('Invalid app context.');
		}
	}
}
