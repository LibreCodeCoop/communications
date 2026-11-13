<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Event;

use OCP\EventDispatcher\Event;

/**
 * Integration providers should register capabilities here only if their server-side adapter actually emits those events into EventStore. The catalog is metadata; it never executes arbitrary dynamic callbacks.
 */
class RegisterTriggerCapabilitiesEvent extends Event {
	private array $sources = [];

	public function addSource(array $source): void {
		if (count($this->sources) >= 100) {
			return;
		}
		$this->sources[] = $source;
	}

	public function getSources(): array {
		return $this->sources;
	}
}
