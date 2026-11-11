<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Notification;

use OCA\Communications\Service\BoardService;
use OCA\Communications\Service\PublicationLink;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;
use OCP\Notification\AlreadyProcessedException;

/** Nextcloud-native notification renderer: re-checks audience at every read. */
final class BoardNotifier implements INotifier {
 public function __construct(
  private BoardService $board,
  private IURLGenerator $urls,
  private IUserManager $users,
  private \OCP\IL10N $l10n,
  private \OCP\L10N\IFactory $l10nFactory,
 ) {}
 public function getID():string { return 'communications'; }
 public function getName():string { return $this->l10n->t('Announcement board'); }

 public function prepare(INotification $notification,string $languageCode):INotification {
  $localized = $this->l10nFactory->get('communications', $languageCode);
  if ($notification->getApp() !== 'communications') {
   throw new UnknownNotificationException();
  }
  $subject=$notification->getSubject();
  $type=$notification->getObjectType();
  if ($subject==='mention' && $type==='cc_board_comment') {
   $item=$this->board->notificationAllowed($notification->getUser(),(int)$notification->getObjectId());
   if ($item===null) { throw new AlreadyProcessedException(); }
   $author=$this->users->getDisplayName($item['actor']) ?: $item['actor'];
   $notification->setParsedSubject($author.$localized->t(' mentioned you on the announcement board'))
    ->setParsedMessage($localized->t('Open the announcement to read and reply.'));
   $id=$item['campaignId'];
  } elseif ($subject==='published' && $type==='cc_board_announcement') {
   $id=(int)$notification->getObjectId();
   $item=$this->board->view($notification->getUser(),$id);
   if ($item===null) { throw new AlreadyProcessedException(); }
   $notification->setParsedSubject($localized->t('New announcement: ').$item['headline'])
    ->setParsedMessage(mb_substr(trim(strip_tags($item['message'])),0,350));
  } else {
   throw new UnknownNotificationException();
  }

  return $notification
   ->setLink(PublicationLink::absolute($this->urls,$id))
   ->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath('communications','app.svg')));
 }
}
