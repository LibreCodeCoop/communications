<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\BackgroundJob;

use OCA\Communications\Service\BoardPublicationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\QueuedJob;

/** Deliver up to 100 recipients; enqueue another job when more remain. */
final class BoardBroadcastJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private BoardPublicationService $publisher,
	) {
		parent::__construct($time);
	}
	protected function run($argument):void {
		if (!is_array($argument)) {
			return;
		}
		$this->publisher->broadcast(
			(int)($argument['id'] ?? 0),
			(int)($argument['version'] ?? 0),
			(int)($argument['offset'] ?? 0)
		);
	}
}
