<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\BackgroundJob;

use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EventStore;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\AppFramework\Utility\ITimeFactory;

class CleanupJob extends TimedJob {
 public function __construct(
  ITimeFactory $time,
  private DeliveryStore $deliveries,
  private EventStore $events,
 ) {
  parent::__construct($time);
  $this->setInterval(86400);
  $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
 }
 protected function run($argument): void {
  $cutoff = $this->time->getTime() - EventStore::RETENTION_SECONDS;
  $this->deliveries->prune($cutoff);
  $this->events->prune($cutoff);
 }
}
