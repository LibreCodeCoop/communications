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

/** One authenticated/validated event subject, never a global audience fan-out. */
final class EventChannelDeliveryJob extends QueuedJob {
	public function __construct(
		ITimeFactory $time,
		private BoardPublicationService $publisher,
	) {
		parent::__construct($time);
	}
	protected function run($argument):void {
		if (!is_array($argument) || !is_int($argument['eventId'] ?? null)
	  || $argument['eventId'] <= 0) {
			return;
		}
		$this->publisher->dispatchEvent($argument['eventId']);
	}
}
