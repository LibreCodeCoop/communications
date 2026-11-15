<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Notification\IManager as NotificationManager;

/** Bounded, persistent announcement history with server-side audience checks. */
class BoardService {
	public const TABLE = 'cc_board_comments';
	public function __construct(
		private CampaignRepository $campaigns,
		private IDBConnection $db,
		private IUserManager $users,
		private IGroupManager $groups,
		private IConfig $config,
		private NotificationManager $notifications,
		private \OCP\IL10N $l10n,
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

	/** Recorded event-triggered Inbox receipt, indexed by campaign and epoch. */
	private function eventInboxReceipts(string $uid):array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('campaign_id', 'version')->from('cc_board_sent')->groupBy('campaign_id', 'version')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter('inbox')))
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['claimed','sent'], IQueryBuilder::PARAM_STR_ARRAY)))
			->executeQuery();
		$keys = [];
		foreach ($rs->fetchAllAssociative() as $row) {
			$keys[(int)$row['campaign_id'] . ':' . (int)$row['version']] = true;
		}
		$rs->closeCursor();
		return $keys;
	}

	/** Keyset pagination of true impressions, preserving the oldest receipts. */
	private function deliveredAt(string $uid):array {
		$map = [];
		$before = null;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id', 'campaign_id', 'impression_at')->from('cc_deliveries')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->andWhere($qb->expr()->isNotNull('impression_at'))
				->orderBy('id', 'DESC')->setMaxResults(500);
			if ($before !== null) {
				$qb->andWhere($qb->expr()->lt('id', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
			}
			$rs = $qb->executeQuery();
			$rows = $rs->fetchAllAssociative();
			$rs->closeCursor();
			foreach ($rows as $row) {
				$before = (int)$row['id'];
				$id = (int)$row['campaign_id'];
				$map[$id] = max($map[$id] ?? 0, (int)$row['impression_at']);
			}
		} while (count($rows) === 500);
		return $map;
	}

	/** Keyset pagination of user reading state, no first-2000 truncation. */
	private function readAt(string $uid):array {
		$map = [];
		$after = null;
		do {
			$qb = $this->db->getQueryBuilder();
			$qb->select('campaign_id', 'read_at')->from('cc_inbox_reads')
				->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
				->orderBy('campaign_id', 'ASC')->setMaxResults(500);
			if ($after !== null) {
				$qb->andWhere($qb->expr()->gt('campaign_id', $qb->createNamedParameter($after, IQueryBuilder::PARAM_INT)));
			}
			$rs = $qb->executeQuery();
			$rows = $rs->fetchAllAssociative();
			$rs->closeCursor();
			foreach ($rows as $row) {
				$after = (int)$row['campaign_id'];
				$map[$after] = (int)$row['read_at'];
			}
		} while (count($rows) === 500);
		return $map;
	}

	private function countComments(int $id): int {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select($qb->func()->count('id'))->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$n = (int)$rs->fetchOne();
		$rs->closeCursor();
		return $n;
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
			'commentCount' => $this->countComments((int)$c['id']),
			'textAlign' => $c['text_align'], 'textColor' => $c['text_color'],
			'author' => $this->users->getDisplayName($c['created_by']) ?: $c['created_by'],
			'category' => $c['category'],
		];
	}

	/** Filter access before searching, counting and paginating, including historic deliveries. */
	public function list(string $uid, int $page = 1, string $search = '', string $filter = 'all'):array {
		$page = max(1, min(100000, $page));
		$search = mb_strtolower(mb_substr(trim($search), 0, 80));
		$receipts = $this->deliveredAt($uid);
		$eventInbox = $this->eventInboxReceipts($uid);
		$reads = $this->readAt($uid);
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
		usort($found, fn (array $a, array $b):int
			=> (($b['published_at'] ?: ($receipts[(int)$b['id']] ?? $b['created_at']))
	  <=> ($a['published_at'] ?: ($receipts[(int)$a['id']] ?? $a['created_at'])))
		?: ($b['id'] <=> $a['id']));
		$total = count($found);
		$size = 10;
		$slice = array_slice($found, ($page - 1) * $size, $size);
		return [
			'announcements' => array_map(fn (array $c) => $this->payload($c, $uid,
				$receipts[(int)$c['id']] ?? null, $reads[(int)$c['id']] ?? null), $slice),
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
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id')->from('cc_board_sent')
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter((int)$c['id'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('version', $qb->createNamedParameter((int)$c['published_at'], IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
		 // The notification renderer may run synchronously while the row is claimed.
			->andWhere($qb->expr()->in('status', $qb->createNamedParameter(['claimed','sent'], IQueryBuilder::PARAM_STR_ARRAY)))
			->setMaxResults(1)->executeQuery();
		$authorized = $rs->fetchOne() !== false;
		$rs->closeCursor();
		return $authorized;
	}

	public function view(string $uid, int $id):?array {
		$c = $this->campaigns->find($id);
		if (!$c) {
			return null;
		}
		$receipts = $this->deliveredAt($uid);
		if (!$this->eligible($c, $uid, $this->eventInboxReceipts($uid))
			&& !$this->outboundFor($uid, $c)) {
			return null;
		}
		$reads = $this->readAt($uid);
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
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('read_at')->from('cc_inbox_reads')
			->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->executeQuery();
		$existing = $rs->fetchOne();
		$rs->closeCursor();
		if ($existing !== false) {
			return true;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->insert('cc_inbox_reads')->values([
			'uid' => $qb->createNamedParameter($uid),
			'campaign_id' => $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			'read_at' => $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT),
		])->executeStatement();
		return true;
	}

	public function comments(string $uid, int $id, int $before = 0): ?array {
		if ($this->view($uid, $id) === null) {
			return null;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'uid', 'message', 'created_at')->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'));
		if ($before > 0) {
			$qb->andWhere($qb->expr()->lt('id', $qb->createNamedParameter($before, IQueryBuilder::PARAM_INT)));
		}
		$rs = $qb->orderBy('id', 'DESC')->setMaxResults(21)->executeQuery();
		$rows = $rs->fetchAllAssociative();
		$rs->closeCursor();
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
			throw new InvalidArgumentException($this->l10n->t('Announcement unavailable.'));
		}
		if (!$c['commentsEnabled']) {
			throw new InvalidArgumentException($this->l10n->t('Comments are disabled.'));
		}
		$message = trim($message);
		if ($message === '' || mb_strlen($message) > 4000) {
			throw new InvalidArgumentException($this->l10n->t('Enter a comment of at most 4,000 characters.'));
		}
		$now = time();
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('id')->from(self::TABLE)
			->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('uid', $qb->createNamedParameter($uid)))
			->andWhere($qb->expr()->gte('created_at', $qb->createNamedParameter($now - 5, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1)->executeQuery();
		$recent = $rs->fetchOne();
		$rs->closeCursor();
		if ($recent !== false) {
			throw new InvalidArgumentException($this->l10n->t('Wait a few seconds before commenting again.'));
		}
		$insert = $this->db->getQueryBuilder();
		$insert->insert(self::TABLE)->values([
			'campaign_id' => $insert->createNamedParameter($id, IQueryBuilder::PARAM_INT),
			'uid' => $insert->createNamedParameter($uid),
			'message' => $insert->createNamedParameter($message),
			'created_at' => $insert->createNamedParameter($now, IQueryBuilder::PARAM_INT),
			'updated_at' => $insert->createNamedParameter($now, IQueryBuilder::PARAM_INT),
		])->executeStatement();
		$commentId = (int)$this->db->lastInsertId(self::TABLE);
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
			} catch (\Throwable $ignored) {
				// A notification failure must never cancel a stored comment.
			}
		}
	}

	public function deleteComment(string $uid, int $campaignId, int $commentId):bool {
		if ($this->view($uid, $campaignId) === null) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('uid')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$author = $rs->fetchOne();
		$rs->closeCursor();
		if ($author === false || ($author !== $uid && !$this->groups->isAdmin($uid))) {
			return false;
		}
		$qb = $this->db->getQueryBuilder();
		return $qb->update(self::TABLE)
			->set('deleted_at', $qb->createNamedParameter(time(), IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeStatement() === 1;
	}

	public function notificationAllowed(string $uid, int $commentId):?array {
		$qb = $this->db->getQueryBuilder();
		$rs = $qb->select('campaign_id', 'uid')->from(self::TABLE)
			->where($qb->expr()->eq('id', $qb->createNamedParameter($commentId, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->isNull('deleted_at'))->executeQuery();
		$row = $rs->fetchAssociative();
		$rs->closeCursor();
		if (!$row || $this->view($uid, (int)$row['campaign_id']) === null) {
			return null;
		}
		return ['campaignId' => (int)$row['campaign_id'],'actor' => $row['uid']];
	}
}
