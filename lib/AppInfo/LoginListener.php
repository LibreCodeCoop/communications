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
use OCP\IRequest;
use OCP\User\Events\UserLoggedInEvent;
use Psr\Log\LoggerInterface;

class LoginListener implements IEventListener {
	public function __construct(
		private EventStore $events,
		private IRequest $request,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if (!$event instanceof UserLoggedInEvent) {
			return;
		}
		// Do not count API/WebDAV Basic authentication as interactive login.
		if ($this->request->getMethod() !== 'POST'
			|| !str_ends_with($this->request->getPathInfo(), '/login')) {
			return;
		}
		try {
			$this->events->record($event->getUser()->getUID(), 'nextcloud.user.login');
		} catch (\Throwable $e) {
			$this->logger->warning('Contextual campaigns login event could not be persisted', ['exception' => $e]);
		}
	}
}
