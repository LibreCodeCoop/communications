<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Lab-only invocation: php dispatch_channel_only.php CAMPAIGN_ID VERSION Replace every external transport with an in-process fake; never send mail, push, activity events, WhatsApp or SMS outside this process.
 */

require_once '/var/www/html/lib/base.php';

use OCA\Communications\Notification\BoardNotifier;
use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\BoardService;
use OCA\Communications\Service\GatewayTransport;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Mail\IMessage;
use OCP\Notification\INotification;

class LabMailerV27 extends \OC\Mail\Mailer {
	public array $messages = [];
	public bool $forceFailure = false;
	public bool $simulateAmbiguousSubmission = false;
	public function __construct() {
	}
	public function createMessage():\OC\Mail\Message {
		return new \OC\Mail\Message(new \Symfony\Component\Mime\Email(), false);
	}
	public function send(IMessage $message):array {
		$this->messages[] = [
			'subject' => $message->getSubject(),
			'body' => $message->getPlainBody(),
			'recipientCount' => count($message->getTo()),
		];
		if ($this->simulateAmbiguousSubmission) {
			throw new \RuntimeException('Synthetic timeout after SMTP DATA');
		}
		return $this->forceFailure ? ['undeliverable'] : [];
	}
}
class LabAppManagerV27 extends \OC\App\AppManager {
	public function __construct() {
	}
	public function isEnabledForUser($appId, $user = null) {
		return in_array($appId, ['notifications','activity'], true);
	}
}
class LabNotificationsV27 extends \OC\Notification\Manager {
	public array $messages = [];
	public function __construct(
		private BoardNotifier $renderer,
		private \OCP\Notification\IManager $real,
	) {
	}
	public function createNotification():INotification {
		return $this->real->createNotification();
	}
	public function notify(INotification $notification):void {
		// Simulate synchronous rendering while the outbox row is still claimed.
		$rendered = $this->renderer->prepare($notification, 'pt_BR');
		$this->messages[] = [
			'uid' => $notification->getUser(),
			'subject' => $rendered->getParsedSubject(),
			'link' => $rendered->getLink(),
		];
	}
}
class LabGatewayV27 extends GatewayTransport {
	public array $messages = [];
	public function __construct() {
	}
	public function send(string $gateway, string $destination, string $body):void {
		$this->messages[] = ['provider' => $gateway,'destination' => $destination,'body' => $body];
	}
}

$id = (int)($argv[1] ?? 0);
$version = (int)($argv[2] ?? 0);
if ($id <= 0 || $version <= 0) {
	throw new \InvalidArgumentException('Fixture campaign and version required');
}
$server = \OC::$server;
$campaign = $server->get(\OCA\Communications\Service\CampaignRepository::class)->find($id);
if (!$campaign || (int)$campaign['published_at'] !== $version || $campaign['board_visible']
	|| $campaign['presentation'] !== 'none' || !str_starts_with($campaign['name'], 'OnlyChannel v27 ')) {
	throw new \InvalidArgumentException('Refuse non-fixture or non-channel-only campaign');
}
$board = $server->get(BoardService::class);
$before = $board->view('poc_second', $id) !== null;
$mail = new LabMailerV27();
$mail->forceFailure = ($argv[3] ?? '') === 'smtp-failed';
$mail->simulateAmbiguousSubmission = ($argv[3] ?? '') === 'smtp-unknown';
$notify = new LabNotificationsV27(
	$server->get(BoardNotifier::class),
	$server->get(\OCP\Notification\IManager::class)
);
$gateway = new LabGatewayV27();
$publisher = $server->get(BoardPublicationService::class);
(new ReflectionProperty(BoardPublicationService::class, 'mailer'))->setValue($publisher, $mail);
(new ReflectionProperty(BoardPublicationService::class, 'notifications'))->setValue($publisher, $notify);
(new ReflectionProperty(BoardPublicationService::class, 'apps'))->setValue($publisher, new LabAppManagerV27());
(new ReflectionProperty(BoardPublicationService::class, 'gatewayTransport'))->setValue($publisher, $gateway);

$user = $server->get(\OCP\IUserManager::class)->get('poc_second');
if (!$user) {
	throw new \RuntimeException('Missing test user');
}
$original = $user->getEMailAddress();
if (!filter_var($original, FILTER_VALIDATE_EMAIL)) {
	$user->setEMailAddress('poc-second@example.test');
}
try {
	$due = (new ReflectionMethod(BoardPublicationService::class, 'due'))->invoke($publisher, $campaign, time());
	(new ReflectionMethod(BoardPublicationService::class, 'ensurePublication'))->invoke($publisher, $id, $version);
	$first = $publisher->broadcast($id, $version);
	$second = $publisher->broadcast($id, $version);
	$after = $board->view('poc_second', $id);
	$blocked = $board->view('poc_admin', $id);
	$inbox = $board->list('poc_second', 1, 'OnlyChannel v27', 'all');
	$read = $after !== null ? $board->markRead('poc_second', $id) : false;
	$db = $server->get(\OCP\IDBConnection::class);
	$qb = $db->getQueryBuilder();
	$rs = $qb->select('uid', 'channel', 'status', 'attempts', 'last_error')->from('cc_board_sent')
		->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
		->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
		->executeQuery();
	$rows = $rs->fetchAllAssociative();
	$rs->closeCursor();
	echo json_encode([
		'due' => $due,'beforeView' => $before,'afterView' => $after !== null,
		'afterSource' => $after['source'] ?? null,
		'outsiderCanView' => $blocked !== null,'inboxTotal' => $inbox['total'],
		'detailMarkRead' => $read,'mail' => $mail->messages,
		'notify' => $notify->messages,'gateway' => $gateway->messages,
		'rows' => $rows,'first' => $first,'second' => $second,
	], JSON_THROW_ON_ERROR);
} finally {
	if (!filter_var($original, FILTER_VALIDATE_EMAIL)) {
		$user->setEMailAddress(is_string($original)?$original:'');
	}
}
