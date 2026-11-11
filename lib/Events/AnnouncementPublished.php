<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Events;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IWebhookCompatibleEvent;

/** Public event dispatched when a board announcement becomes available. */
final class AnnouncementPublished extends Event implements IWebhookCompatibleEvent {
 public function __construct(
  private int $campaignId,
  private int $publicationVersion,
  private string $subject,
  private string $message,
  private ?int $scheduleTime,
  private ?int $deleteTime,
 ) { parent::__construct(); }

 public function getCampaignId(): int { return $this->campaignId; }
 public function getPublicationVersion(): int { return $this->publicationVersion; }

 public function getWebhookSerializable(): array {
  return [
   'campaignId' => $this->campaignId,
   'publicationId' => $this->campaignId . ':' . $this->publicationVersion,
   'subject' => $this->subject,
   'message' => $this->message,
   'plainMessage' => trim(preg_replace('/[\r\n]+/', "\n", strip_tags($this->message))),
   'scheduleTime' => $this->scheduleTime,
   'deleteTime' => $this->deleteTime,
  ];
 }
}
