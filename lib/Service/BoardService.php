<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCA\Communications\Db\BoardMapper;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;
use Psr\Log\LoggerInterface;

/** Bounded, persistent announcement history with server-side audience checks. */
class BoardService {
	public const TABLE = BoardMapper::TABLE;
	public function __construct(
		private CampaignRepository $campaigns,
		private BoardMapper $mapper,
		private IUserManager $users,
		private IGroupManager $groups,
		private IConfig $config,
		private NotificationManager $notifications,
		private LoggerInterface $logger,
		private IL10N $l10n,
	) {
	}

	/**
	 * Only Inbox-enabled publications may enter its list. For event-triggered
	 * ones, the named recipient must also have an actual Inbox delivery
	 * recorded after the event. A visual impression does not opt someone
	 * into Inbox history.
	 */
	private function eligible(array $c, string $uid, array $eventInbox = []):bool {
		if (!$c['board_visible'] || !$this->campaigns->isEligible($c, $uid, time())) {
			return false;
		}
		if (!in_array($c['trigger_key'], ['nextcloud.board.published','nextcloud.time.window'], true)
			&& !isset($eventInbox[(int)$c['id'] . ':' . (int)$c['published_at']])) {
			return false;
		}
		return !($c['category'] === 'sponsored'
			&& $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1');
	}

	private function payload(array $c, string $uid, ?int $deliveredAt = null, ?int $readAt = null):array {
		$c = CampaignRepository::localize($c, $this->config->getUserValue($uid, 'core', 'lang', ''));
		return [
			'id' => $c['id'], 'headline' => $c['headline'], 'message' => $c['message'],
			'ctaLabel' => $c['cta_label'], 'ctaUrl' => $c['cta_url'],
			'publishedAt' => $c['published_at'] ?: ($deliveredAt ?: $c['created_at']),
			'deliveredAt' => $deliveredAt,'readAt' => $readAt,
			'source' => $c['board_visible'] ? 'publicacao' : ($deliveredAt !== null?'contextual':'canal'),
			'startsAt' => $c['starts_at'],
			'endsAt' => $c['ends_at'], 'inInbox' => $c['board_visible'],
			'commentsEnabled' => $c['board_visible'] && $c['comments_enabled'],
			'commentCount' => $this->mapper->countComments((int)$c['id']),
			'textAlign' => $c['text_align'], 'textColor' => $c['text_color'],
			'author' => $this->users->getDisplayName($c['created_by']) ?: $c['created_by'],
			'category' => $c['category'],
		];
	}

	/** Filter access before searching, counting and paginating, including historic deliveries. */
	public function list(string $uid, int $page = 1, string $search = '', string $filter = 'all'):array {
		$page = max(1, min(100000, $page));
		$search = mb_strtolower(mb_substr(trim($search), 0, 80));
		$receipts = $this->mapper->deliveredAt($uid);
		$eventInbox = $this->mapper->eventInboxReceipts($uid);
		$reads = $this->mapper->readAt($uid);
		$found = [];
		$unreadTotal = 0;
		foreach ($this->campaigns->scan() as $c) {
			$id = (int)$c['id'];
			if (!$this->eligible($c, $uid, $eventInbox)) {
				continue;
			}
			$unread = !(isset($reads[$id]) && $reads[$id] > 0);
			if ($unread) {
				$unreadTotal++;
			}
			if ($filter === 'unread' && !$unread) {
				continue;
			}
			$localized = CampaignRepository::localize($c, $this->config->getUserValue($uid, 'core', 'lang', ''));
			if ($search !== '' && !str_contains(mb_strtolower(
				$c['headline'] . ' ' . $c['message'] . ' ' . $localized['headline'] . ' ' . $localized['message']), $search)) {
				continue;
			}
			$found[] = $c;
		}
		$found = InboxOrderingPolicy::newestFirst($found, $receipts);
		$total = count($found);
		$size = 10;
		$slice = array_slice($found, ($page - 1) * $size, $size);
		$announcements = [];
		foreach ($slice as $campaign) {
			$id = (int)$campaign['id'];
			$announcements[] = $this->payload($campaign, $uid, $receipts[$id] ?? null, $reads[$id] ?? null);
		}
		return [
			'announcements' => $announcements,
			'unreadTotal' => $unreadTotal,
			'page' => $page,'pageSize' => $size,'total' => $total,'hasMore' => $page * $size < $total,
		];
	}

	/** Direct-link access for an explicitly sent channel-only notification.
	 * Access never promotes the publication into the Inbox list.
	 */
	private function outboundFor(string $uid, array $c):bool {
		if ($c['status'] !== 'active' || $c['published_at'] <= 0
			|| !$this->campaigns->isEligible($c, $uid, time())
			|| ($c['category'] === 'sponsored'
			&& $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1')) {
			return false;
		}
		return $this->mapper->hasSentReceipt((int)$c['id'], (int)$c['published_at'], $uid);
	}

	public function view(string $uid, int $id):?array {
		$c = $this->campaigns->find($id);
		if (!$c) {
			return null;
		}
		$receipts = $this->mapper->deliveredAt($uid);
		if (!$this->eligible($c, $uid, $this->mapper->eventInboxReceipts($uid))
			&& !$this->outboundFor($uid, $c)) {
			return null;
		}
		$reads = $this->mapper->readAt($uid);
		return $this->payload($c, $uid, $receipts[$id] ?? null, $reads[$id] ?? null);
	}

	/** Explicit user action; GET requests never change inbox state. */
	public function markRead(string $uid, int $id):bool {
		if ($this->view($uid, $id) === null) {
			return false;
		}
		$campaign = $this->campaigns->find($id);
		if ($campaign !== null && !$campaign['board_visible']) {
			// Direct-only links are readable but do not create Inbox read receipts.
			return true;
		}
		return $this->mapper->markRead($uid, $id, time());
	}

	public function comments(string $uid, int $id, int $before = 0): ?array {
		if ($this->view($uid, $id) === null) {
			return null;
		}
		$rows = $this->mapper->commentPage($id, $before);
		$more = count($rows) > 20;
		$rows = array_slice($rows, 0, 20);
		$items = array_map(function (array $row) use ($uid, $id):array {
			return ['id' => (int)$row['id'],'campaignId' => $id,'uid' => $row['uid'],
				'author' => $this->users->getDisplayName($row['uid']) ?: $row['uid'],
				'message' => $row['message'],'createdAt' => (int)$row['created_at'],
				'canDelete' => $uid === $row['uid'] || $this->groups->isAdmin($uid)];
		}, $rows);
		return ['comments' => $items,'hasMore' => $more,'nextBefore' => $more ? (int)end($rows)['id'] : null];
	}

	/**
	 * Mention suggestions are restricted to users who can currently read this
	 * particular announcement. No email addresses or global user list returned.
	 */
	public function mentionCandidates(string $uid, int $id, string $search):?array {
		$item = $this->view($uid, $id);
		if ($item === null || !$item['commentsEnabled']) {
			return null;
		}
		$search = trim($search);
		if (mb_strlen($search) < 2 || mb_strlen($search) > 40
			|| !preg_match('/^[A-Za-z0-9_.-]{2,40}$/D', $search)) {
			return [];
		}
		$campaign = $this->campaigns->find($id);
		if ($campaign === null || !$campaign['board_visible']) {
			return [];
		}
		$results = [];
		foreach ([$this->users->search($search, 30),
			$this->users->searchDisplayName($search, 30)] as $matches) {
			foreach ($matches as $user) {
				$candidate = $user->getUID();
				if ($candidate === $uid || isset($results[$candidate])
					|| !$this->campaigns->isEligible($campaign, $candidate, time())
					|| ($campaign['category'] === 'sponsored'
					&& $this->config->getUserValue($candidate, 'communications', 'sponsored_optout', '0') === '1')) {
					continue;
				}
				$results[$candidate] = [
					'id' => $candidate,'displayName' => $user->getDisplayName(),
				];
				if (count($results) >= 10) {
					break 2;
				}
			}
		}
		return array_values($results);
	}

	public function createComment(string $uid, int $id, string $message): array {
		$c = $this->view($uid, $id);
		if ($c === null) {
			// TRANSLATORS Short label for an option or field in the BoardService service.
			throw new InvalidArgumentException($this->l10n->t('Announcement unavailable.'));
		}
		if (!$c['commentsEnabled']) {
			// TRANSLATORS Short label for an option or field in the BoardService service.
			throw new InvalidArgumentException($this->l10n->t('Comments are disabled.'));
		}
		$message = trim($message);
		if ($message === '' || mb_strlen($message) > 4000) {
			// TRANSLATORS Action or instruction in the BoardService service.
			throw new InvalidArgumentException($this->l10n->t('Enter a comment of at most 4,000 characters.'));
		}
		$now = time();
		if ($this->mapper->hasRecentComment($id, $uid, $now - 5)) {
			// TRANSLATORS Descriptive message shown in the BoardService service.
			throw new InvalidArgumentException($this->l10n->t('Wait a few seconds before commenting again.'));
		}
		$commentId = $this->mapper->insertComment($id, $uid, $message, $now);
		$this->notifyMentions($uid, $id, $commentId, $message);
		return ['id' => $commentId,'campaignId' => $id,'uid' => $uid,
			'author' => $this->users->getDisplayName($uid) ?: $uid,
			'message' => $message,'createdAt' => $now,'canDelete' => true];
	}

	private function notifyMentions(string $actor, int $campaignId, int $commentId, string $message):void {
		preg_match_all('/(?<![\w@])@([A-Za-z0-9_.-]{2,64})/u', $message, $match);
		foreach (array_slice(array_unique($match[1]), 0, 10) as $uid) {
			if ($uid === $actor || $this->users->get($uid) === null
				|| $this->view($uid, $campaignId) === null) {
				continue;
			}
			try {
				$n = $this->notifications->createNotification();
				$n->setApp('communications')->setUser($uid)
					->setDateTime(new \DateTime())
					->setObject('cc_board_comment', (string)$commentId)
					->setSubject('mention', [$actor,(string)$campaignId]);
				$this->notifications->notify($n);
			} catch (\Throwable $error) {
				$this->logger->warning('Mention notification failed', ['exceptionClass' => $error::class]);
			}
		}
	}

	public function deleteComment(string $uid, int $campaignId, int $commentId):bool {
		if ($this->view($uid, $campaignId) === null) {
			return false;
		}
		$author = $this->mapper->commentAuthor($campaignId, $commentId);
		if ($author === false || ($author !== $uid && !$this->groups->isAdmin($uid))) {
			return false;
		}
		return $this->mapper->deleteComment($campaignId, $commentId, time());
	}

	public function notificationAllowed(string $uid, int $commentId):?array {
		$row = $this->mapper->commentForNotification($commentId);
		if (!$row || $this->view($uid, (int)$row['campaign_id']) === null) {
			return null;
		}
		return ['campaignId' => (int)$row['campaign_id'],'actor' => $row['uid']];
	}
}
