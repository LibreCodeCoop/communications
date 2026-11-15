<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Activity;

use OCA\Communications\Service\BoardService;
use OCA\Communications\Service\PublicationLink;
use OCP\Activity\Exceptions\UnknownActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use UnexpectedValueException;

/** The activity stream does not reveal inaccessible or expired posts. */
final class BoardProvider implements IProvider {
	public function __construct(
		private BoardService $board,
		private IManager $activities,
		private IURLGenerator $urls,
		private IL10N $l10n,
		private IFactory $l10nFactory,
	) {
	}

	public function parse($language, IEvent $event, ?IEvent $previousEvent = null):IEvent {
		$localized = $this->l10nFactory->get('communications', (string)$language);
		if ($event->getApp() !== 'communications'
			|| $event->getSubject() !== 'board_published'
			|| $event->getObjectType() !== 'cc_board_announcement') {
			throw new UnknownActivityException();
		}
		try {
			$uid = $this->activities->getCurrentUserId();
		} catch (UnexpectedValueException $error) {
			throw new UnknownActivityException('Missing activity viewer', 0, $error);
		}
		if ($event->getAffectedUser() !== $uid) {
			throw new UnknownActivityException('This activity belongs to another account');
		}
		$post = $this->board->view($uid, (int)$event->getObjectId());
		if ($post === null) {
			throw new UnknownActivityException('Announcement is unavailable');
		}
		return $event
			// TRANSLATORS Prefix before an announcement title in an Activity or notification entry.
			->setParsedSubject($localized->t('New announcement: ') . $post['headline'])
			->setParsedMessage(mb_substr(trim(strip_tags($post['message'])), 0, 350))
			->setLink(PublicationLink::absolute($this->urls, (int)$post['id']))
			->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath('communications', 'app.svg')));
	}
}
