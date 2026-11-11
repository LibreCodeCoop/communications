<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\BackgroundJob;

use OCA\Communications\Service\BoardPublicationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;

/** Activate scheduled announcements and enqueue optional delivery channels. */
final class BoardPublishJob extends TimedJob {
 public function __construct(ITimeFactory $time, private BoardPublicationService $publisher) {
  parent::__construct($time);
  $this->setInterval(60);
  $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
 }
 protected function run($argument):void {
  $this->publisher->publishDue(100);
  $this->publisher->retryEventDeliveries(100);
 }
}
