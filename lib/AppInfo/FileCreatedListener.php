<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\EventStore;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\File;
use Psr\Log\LoggerInterface;

class FileCreatedListener implements IEventListener {
	public function __construct(
		private EventStore $events,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof NodeCreatedEvent) {
			return;
		}
		$node = $event->getNode();
		if (!$node instanceof File) {
			return;
		}
		$owner = $node->getOwner();
		if ($owner === null || !str_starts_with($node->getPath(), '/' . $owner->getUID() . '/files/')) {
			return;
		}
		try {
			$this->events->record($owner->getUID(), 'nextcloud.files.created', 'files');
		} catch (\Throwable $e) {
			$this->logger->warning('Contextual campaigns file event failed', ['exception' => $e]);
		}
	}
}
