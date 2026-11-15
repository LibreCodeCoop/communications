<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCA\Communications\BackgroundJob\BoardBroadcastJob;
use OCA\Communications\BackgroundJob\EventChannelDeliveryJob;
use OCA\Communications\Events\AnnouncementPublished;
use OCP\Activity\IManager as ActivityManager;
use OCP\App\IAppManager;
use OCP\BackgroundJob\IJobList;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Mail\IMailer;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Bounded board-publication processor and per-recipient channel outbox.
 * No channel is enabled by default. Never fan out inside REST requests.
 */
final class BoardPublicationService {
	private const PUB = 'cc_board_pub';
	private const SENT = 'cc_board_sent';
	private const APP = 'communications';
	private const BATCH = 100;

	public function __construct(
		private CampaignRepository $campaigns,
		private IDBConnection $db,
		private IEventDispatcher $dispatcher,
		private IJobList $jobs,
		private IUserManager $users,
		private NotificationManager $notifications,
		private ActivityManager $activity,
		private IMailer $mailer,
		private IAppManager $apps,
		private IConfig $config,
		private IURLGenerator $urls,
		private LoggerInterface $logger,
		private GatewayTransport $gatewayTransport,
		private GatewayRecipientPreferences $gatewayPreferences,
		private EventStore $events,
	) {
	}

	private function due(array $c, int $now): bool {
		return $c['status'] === 'active'
		 && in_array($c['trigger_key'], ['nextcloud.board.published','nextcloud.time.window'], true)
		 && ($c['board_visible'] || $c['notify_users'] || $c['notify_activity']
	   || $c['notify_email'] || $c['gateway_channels'] !== [])
		 && $c['published_at'] > 0
		 && ($c['starts_at'] === null || $c['starts_at'] <= $now)
		 && ($c['ends_at'] === null || $c['ends_at'] > $now);
	}

	/** @return array<string, mixed>|null */
	private function publication(int $id, int $version): ?array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('*')->from(self::PUB)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();
		return $row === false ? null : $row;
	}

	private function ensurePublication(int $id, int $version): array {
		$existing = $this->publication($id, $version);
		if ($existing !== null) {
			return $existing;
		}
		$qb = $this->db->getQueryBuilder();
		try {
			$qb->insert(self::PUB)->values([
				'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
				'version' => $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT),
				'created_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
			])->executeStatement();
		} catch (\OCP\DB\Exception $e) {
			// A second worker may have inserted the same publication meanwhile.
			if ($this->publication($id, $version) === null) {
				throw $e;
			}
		}
		return $this->publication($id, $version);
	}

	private function markPublication(int $id, int $version, string $field, int $value):void {
		if (!in_array($field, ['event_sent','broadcast_done'], true)) {
			throw new \InvalidArgumentException('Invalid publication field');
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::PUB)
			->set($field, $qb->createNamedParameter($value, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)));
		if ($field === 'event_sent') {
			$qb->set('event_sent_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
		}
		$qb->executeStatement();
	}

	/**
	 * Called by cron (or a local occ smoke command), never from a browser.
	 * A scheduled announcement emits the public event only after its start time.
	 */
	public function publishDue(int $limit = 100):array {
		$limit = max(1, min($limit, 500));
		$stats = ['events' => 0,'enqueued' => 0,'errors' => 0];
		$now = time();
		foreach ($this->campaigns->all() as $campaign) {
			if (!$this->due($campaign, $now)) {
				continue;
			}
			if ($limit * 2 <= $stats['events'] + $stats['enqueued']) {
				break;
			}
			$id = (int)$campaign['id'];
			$version = (int)$campaign['published_at'];
			try {
				$state = $this->ensurePublication($id, $version);
				if ((int)$state['event_sent'] !== 1) {
					// Announce an inbox publication only if one exists; channel-only sends
					// do not create a false webhook/event announcing a board entry.
					if ($campaign['board_visible']) {
						$event = new AnnouncementPublished(
							$id, $version, $campaign['headline'], $campaign['message'],
							$campaign['starts_at'], $campaign['ends_at']
						);
						$this->dispatcher->dispatchTyped($event);
						$stats['events']++;
					}
					$this->markPublication($id, $version, 'event_sent', 1);
				}
				if ((int)$state['broadcast_done'] === 1) {
					continue;
				}
				if (!$campaign['notify_users'] && !$campaign['notify_activity'] && !$campaign['notify_email']
					&& $campaign['gateway_channels'] === []) {
					$this->markPublication($id, $version, 'broadcast_done', 1);
					continue;
				}
				$this->jobs->add(BoardBroadcastJob::class, ['id' => $id,'version' => $version,'offset' => 0]);
				$stats['enqueued']++;
			} catch (\Throwable $e) {
				$stats['errors']++;
				$this->logger->error('Failed to publish contextual announcement ' . $id,
					['app' => self::APP,'exception' => $e]);
			}
		}
		return $stats;
	}

	private function sentRow(int $id, int $version, string $uid, string $channel, int $eventId = 0): ?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('*')->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($channel)))
			->andWhere($qb->expr()->eq('event_id', $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		return $row === false ? null : $row;
	}

	private function claim(int $id, int $version, string $uid, string $channel, int $eventId = 0):?array {
		$now = time();
		$row = $this->sentRow($id, $version, $uid, $channel, $eventId);
		if ($row === null) {
			$qb = $this->db->getQueryBuilder();
			try {
				$qb->insert(self::SENT)->values([
					'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
					'version' => $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT),
					'event_id' => $qb->createNamedParameter($eventId, IQueryBuilder::PARAM_INT),
					'uid' => $qb->createNamedParameter($uid),
					'channel' => $qb->createNamedParameter($channel),
					'status' => $qb->createNamedParameter('pending'),
					'created_at' => $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT),
				])->executeStatement();
			} catch (\OCP\DB\Exception $e) {
				if ($this->sentRow($id, $version, $uid, $channel, $eventId) === null) {
					throw $e;
				}
			}
			$row = $this->sentRow($id, $version, $uid, $channel, $eventId);
		}
		if ($row === null || in_array($row['status'], ['sent','skipped','failed','unknown'], true)
	  || (int)$row['attempts'] >= 5 || (int)$row['claim_until'] > $now) {
			return null;
		}
		// If a worker died after contacting SMTP or an external provider, retrying
		// its expired claim could send a duplicate. Preserve unknown for review.
		if ($row['status'] === 'claimed' && ($channel === 'email' || str_starts_with($channel, 'gateway:'))) {
			$this->state((int)$row['id'], 'unknown', 'submission_outcome_unknown');
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$changed = $qb->update(self::SENT)
			->set('status', $qb->createNamedParameter('claimed'))
			->set('attempts', $qb->createNamedParameter(((int)$row['attempts']) + 1, IQueryBuilder::PARAM_INT))
			->set('claim_until', $qb->createNamedParameter($now + 90, IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int)$row['id'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->lte('claim_until', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT)))
			->executeStatement();
		return $changed === 1 ? $row : null;
	}

	private function state(int $rowId, string $status, ?string $error = null, int $backoff = 0):void {
		$qb = $this->db->getQueryBuilder();
		$qb->update(self::SENT)
			->set('status', $qb->createNamedParameter($status))
			->set('claim_until', $qb->createNamedParameter($backoff?time() + $backoff:0, IQueryBuilder::PARAM_INT))
			->set('last_error', $qb->createNamedParameter($error === null?null:mb_substr($error, 0, 248)));
		if ($status === 'sent') {
			$qb->set('sent_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT));
		}
		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($rowId, IQueryBuilder::PARAM_INT)))
			->executeStatement();
	}

	private function deliver(array $c, IUser $user, int $version, string $channel, int $eventId = 0):void {
		$uid = $user->getUID();
		$id = (int)$c['id'];
		$row = $this->claim($id, $version, $uid, $channel, $eventId);
		if ($row === null) {
			return;
		}
		try {
			$sent = match($channel) {
				'notifications' => $this->notify($c, $user),
				'activity' => $this->activity($c, $user, $version),
				'email' => $this->email($c, $user),
				'inbox' => true,
				default => str_starts_with($channel, 'gateway:')
				 ? $this->gateway($c, $user, substr($channel, 8)) : false,
			};
			$this->state((int)$row['id'], $sent?'sent':'skipped');
		} catch (DeliveryOutcomeUnknownException $e) {
			// SMTP delivery follows the same rule: an ambiguous
			// submission never retries automatically without provider idempotency.
			$this->state((int)$row['id'], 'unknown', 'submission_outcome_unknown');
			$this->logger->warning('Delivery submission outcome unknown', [
				'app' => self::APP,'campaignId' => $id,'channel' => $channel,
				'exceptionClass' => $e::class,
			]);
		} catch (\Throwable $e) {
			$attempt = (int)$row['attempts'] + 1;
			// External provider exceptions may include a phone, chat ID or message:
			// neither persist their text nor attach them as logger exceptions.
			$this->state((int)$row['id'], $attempt >= 5?'failed':'retry',
				$e::class, min(300, 30 * $attempt));
			$this->logger->warning('Contextual announcement channel delivery failed',
				['app' => self::APP,'campaignId' => $id,'channel' => $channel,'exceptionClass' => $e::class]);
		}
	}

	private function notify(array $c, IUser $user):bool {
		if (!$this->apps->isEnabledForUser('notifications', $user)) {
			return false;
		}
		$n = $this->notifications->createNotification();
		$n->setApp(self::APP)->setUser($user->getUID())
			->setDateTime(new \DateTime())
			->setObject('cc_board_announcement', (string)$c['id'])
			->setSubject('published', [$c['created_by']]);
		$this->notifications->notify($n);
		return true;
	}

	private function activity(array $c, IUser $user, int $version):bool {
		if (!$this->apps->isEnabledForUser('activity', $user)) {
			return false;
		}
		$event = $this->activity->generateEvent();
		$event->setApp(self::APP)
			->setType(self::APP)
			->setAffectedUser($user->getUID())
			->setAuthor($c['created_by'])
			->setTimestamp($version)
			->setSubject('board_published', [(string)$c['id']])
			->setObject('cc_board_announcement', (int)$c['id']);
		$this->activity->publish($event);
		return true;
	}

	/** URL generation in cron can lack registered app routes; never send a root link. */
	private function inboxLink(int $id):string {
		return PublicationLink::absolute($this->urls, $id);
	}

	private function email(array $c, IUser $user):bool {
		$address = $user->getEMailAddress();
		if (!is_string($address) || !filter_var($address, FILTER_VALIDATE_EMAIL)) {
			return false;
		}
		$email = $this->mailer->createMessage();
		$email->setTo([$address => $user->getDisplayName()]);
		$localized = CampaignRepository::localize($c, $this->config->getUserValue($user->getUID(), 'core', 'lang', ''));
		$email->setSubject($localized['headline']);
		$email->setPlainBody($localized['message'] . "\n\n" . $this->inboxLink((int)$c['id']));
		// IMailer reports failed recipients even when no exception was raised.
		// An e-mail-only publication must not be marked as successfully delivered.
		try {
			$failures = $this->mailer->send($email);
		} catch (\Throwable $error) {
			throw new DeliveryOutcomeUnknownException('SMTP outcome unknown', 0, $error);
		}
		if ($failures !== []) {
			throw new \RuntimeException('Mail transport rejected one or more recipients');
		}
		return true;
	}

	/** Send only when the recipient personally opted in for this gateway. */
	private function gateway(array $c, IUser $user, string $id):bool {
		if (!in_array($id, $c['gateway_channels'], true)) {
			return false;
		}
		$destination = $this->gatewayPreferences->destinationFor(
			$user->getUID(), $id, $c['category']
		);
		if ($destination === null) {
			return false;
		}
		$localized = CampaignRepository::localize(
			$c, $this->config->getUserValue($user->getUID(), 'core', 'lang', '')
		);
		$body = $localized['headline'] . "\n" . $localized['message'] . "\n\n"
		 . $this->inboxLink((int)$c['id']);
		// Bound body size; no media, HTML or secrets from the document/event.
		$this->gatewayTransport->send($id, $destination, mb_substr($body, 0, 5000));
		return true;
	}

	/** Each job handles only 100 accounts and then queues its successor. */
	public function broadcast(int $id, int $version, int $offset = 0):array {
		$status = ['processed' => 0,'queued' => false,'done' => false];
		$c = $this->campaigns->find($id);
		$offset = max(0, $offset);
		if ($c === null || (int)$c['published_at'] !== $version || !$this->due($c, time())) {
			$this->markPublication($id, $version, 'broadcast_done', 1);
			return $status;
		}
		$accounts = $this->users->search('', self::BATCH, $offset);
		foreach ($accounts as $user) {
			if (!$user->isEnabled() || !$this->campaigns->isEligible($c, $user->getUID(), time())) {
				continue;
			}
			if ($c['category'] === 'sponsored'
	   && $this->config->getUserValue($user->getUID(), self::APP, 'sponsored_optout', '0') === '1') {
				continue;
			}
			foreach (['notify_users' => 'notifications','notify_activity' => 'activity','notify_email' => 'email'] as $key => $channel) {
				if (!$c[$key]) {
					continue;
				}
				if ($user->getUID() === $c['created_by'] && $channel !== 'activity') {
					continue;
				}
				$this->deliver($c, $user, $version, $channel);
			}
			// A creator can receive these only through their own opt-in.
			foreach ($c['gateway_channels'] as $gatewayId) {
				if (!isset(GatewayTransport::LABELS[$gatewayId])) {
					continue;
				}
				if ($this->gatewayPreferences->destinationFor($user->getUID(), $gatewayId, $c['category']) === null) {
					continue;
				}
				$this->deliver($c, $user, $version, 'gateway:' . $gatewayId);
			}
			$status['processed']++;
		}
		if (count($accounts) === self::BATCH) {
			$this->jobs->add(BoardBroadcastJob::class,
				['id' => $id,'version' => $version,'offset' => $offset + self::BATCH]);
			$status['queued'] = true;
		} else {
			$qb = $this->db->getQueryBuilder();
			$rs = $qb->select($qb->func()->count('id'))->from(self::SENT)
				->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
				->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['retry','claimed','pending'], IQueryBuilder::PARAM_STR_ARRAY)))
				->executeQuery();
			$pending = (int)$rs->fetchOne();
			$rs->closeCursor();
			if ($pending === 0) {
				$this->markPublication($id, $version, 'broadcast_done', 1);
				$status['done'] = true;
			}
		}
		return $status;
	}

	/**
	 * A recorded event has ONE trusted subject. Never fan out to all users.
	 * Webhook/Flow/page events are tied to a specific campaign by context_app.
	 * Revalidate audience, lifecycle, sponsored opt-out and timing at dispatch.
	 */
	public function dispatchEvent(int $eventId):array {
		$result = ['matched' => 0,'channels' => 0];
		$event = $this->events->byId($eventId);
		if ($event === null || in_array($event['trigger_key'], [
			'nextcloud.board.published','nextcloud.time.window'], true)) {
			return $result;
		}
		$uid = $event['uid'];
		$user = $this->users->get($uid);
		if ($user === null || !$user->isEnabled()) {
			return $result;
		}
		$now = time();
		foreach ($this->campaigns->activeForTrigger($event['trigger_key']) as $campaign) {
			$id = (int)$campaign['id'];
			$version = (int)$campaign['published_at'];
			if ($version <= 0 || $event['created_at'] < max((int)$campaign['created_at'], $version)
	   || ($campaign['starts_at'] !== null && $event['created_at'] < (int)$campaign['starts_at'])
	   || ($campaign['ends_at'] !== null && $event['created_at'] >= (int)$campaign['ends_at'])
	   || !$this->campaigns->isEligible($campaign, $uid, $now)) {
				continue;
			}
			if (in_array($event['trigger_key'], ['external.webhook','nextcloud.flow.matched','nextcloud.page.opened'], true)
	   && $event['context_app'] !== 'campaign_' . $id) {
				continue;
			}
			if ($campaign['category'] === 'sponsored'
	   && $this->config->getUserValue($uid, self::APP, 'sponsored_optout', '0') === '1') {
				continue;
			}
			$result['matched']++;
			$channels = [];
			if ($campaign['board_visible']) {
				$channels[] = 'inbox';
			}
			if ($campaign['notify_users'] && $uid !== $campaign['created_by']) {
				$channels[] = 'notifications';
			}
			if ($campaign['notify_activity']) {
				$channels[] = 'activity';
			}
			if ($campaign['notify_email'] && $uid !== $campaign['created_by']) {
				$channels[] = 'email';
			}
			foreach ($campaign['gateway_channels'] as $idOfGateway) {
				if (isset(GatewayTransport::LABELS[$idOfGateway])
					&& $this->gatewayPreferences->destinationFor($uid, $idOfGateway, $campaign['category']) !== null) {
					$channels[] = 'gateway:' . $idOfGateway;
				}
			}
			foreach ($channels as $channel) {
				if (!$this->allowedForEvent($campaign, $uid, $channel, $now, $eventId)) {
					continue;
				}
				$this->deliver($campaign, $user, $version, $channel, $eventId);
				$result['channels']++;
			}
		}
		return $result;
	}

	/**
	 * The same per-recipient frequency applies to every channel. Only accepted,
	 * currently claimed or retryable messages consume a send slot.
	 */
	private function allowedForEvent(array $campaign, string $uid, string $channel, int $now, int $eventId):bool {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('event_id', 'created_at', 'sent_at', 'status')->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter((int)$campaign['id'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter((int)$campaign['published_at'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($channel)))
			->andWhere($qb->expr()->gt('event_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$prior = $rs->fetchAllAssociative();
		$rs->closeCursor();
		$times = [];
		foreach ($prior as $row) {
			if ((int)$row['event_id'] === $eventId) {
				return true;
			}
			if (in_array($row['status'], ['sent','claimed','retry','pending','unknown'], true)) {
				$times[] = (int)($row['sent_at'] ?? $row['created_at']);
			}
		}
		return DeliveryFrequencyPolicy::allows($campaign, $times, $now);
	}

	/** Timed cron: retry only submissions known safe to repeat. Unknown is final. */
	public function retryEventDeliveries(int $limit = 100):int {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('event_id')->from(self::SENT)->groupBy('event_id')
			->where($qb->expr()->gt('event_id', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('retry')))
			->andWhere($qb->expr()->lte('claim_until', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT)))
			->setMaxResults(max(1, min($limit, 500)))->executeQuery();
		$ids = array_map('intval', $rs->fetchFirstColumn());
		$rs->closeCursor();
		foreach ($ids as $eventId) {
			$this->jobs->add(EventChannelDeliveryJob::class, ['eventId' => $eventId]);
		}
		return count($ids);
	}

	/** Non-personalized publication overview for admin diagnostics. */
	public function stats(int $id):array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('channel', 'status', $qb->func()->count('id') . ' AS total')
			->from(self::SENT)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->groupBy('channel', 'status')->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
		return $rows;
	}
}
