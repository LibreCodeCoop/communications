<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\EventStore;
use OCP\Comments\Events\CommentAddedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\File;
use OCP\Share\Events\ShareAcceptedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\IShare;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use Psr\Log\LoggerInterface;

class CoreEventsListener implements IEventListener {
	public function __construct(
		private EventStore $events,
		private LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		$uid = null;
		$trigger = null;
		$app = 'any';
		if ($event instanceof UserFirstTimeLoggedInEvent) {
			$uid = $event->getUser()->getUID();
			$trigger = 'nextcloud.user.first_login';
		} elseif ($event instanceof ShareCreatedEvent) {
			$uid = $event->getShare()->getSharedBy();
			$trigger = 'nextcloud.share.created';
			$app = 'files';
		} elseif ($event instanceof ShareAcceptedEvent) {
			$share = $event->getShare();
			if ($share->getShareType() !== IShare::TYPE_USER) {
				return;
			}
			$uid = $share->getSharedWith();
			$trigger = 'nextcloud.share.accepted';
			$app = 'files';
		} elseif ($event instanceof NodeWrittenEvent || $event instanceof NodeDeletedEvent) {
			$node = $event->getNode();
			if (!$node instanceof File) {
				return;
			}
			$owner = $node->getOwner();
			if ($owner === null || !str_starts_with($node->getPath(), '/' . $owner->getUID() . '/files/')) {
				return;
			}
			$uid = $owner->getUID();
			$trigger = $event instanceof NodeWrittenEvent ? 'nextcloud.files.updated' : 'nextcloud.files.deleted';
			$app = 'files';
		} elseif ($event instanceof CommentAddedEvent) {
			$comment = $event->getComment();
			if ($comment->getActorType() !== 'users') {
				return;
			}
			$uid = $comment->getActorId();
			$trigger = 'nextcloud.comment.created';
		}
		if (!is_string($uid) || $uid === '' || $trigger === null) {
			return;
		}
		try {
			$this->events->record($uid, $trigger, $app);
		} catch (\Throwable $e) {
			// Campaign failures must never roll back Files, sharing or comments.
			$this->logger->warning('Contextual campaigns event could not be recorded', ['exception' => $e]);
		}
	}
}
