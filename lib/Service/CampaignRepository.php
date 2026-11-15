<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCA\Communications\Db\CampaignConflictException;
use OCA\Communications\Db\CampaignMapper;
use OCP\IGroupManager;
use OCP\IUserManager;

class CampaignRepository {
	public const TABLE = CampaignMapper::TABLE;
	public const TRIGGERS = [
		'nextcloud.user.login' => 'Interactive login',
		'nextcloud.files.created' => 'File created (Files)',
		'nextcloud.files.updated' => 'File updated (Files)',
		'nextcloud.files.deleted' => 'File removed (Files)',
		'nextcloud.user.first_login' => 'First login',
		'nextcloud.share.created' => 'Share created',
		'nextcloud.share.accepted' => 'Share accepted',
		'nextcloud.comment.created' => 'Comment published',
		'nextcloud.page.opened' => 'Application page opened',
		'nextcloud.time.window' => 'Date-scheduled campaign',
		'nextcloud.flow.matched' => 'Nextcloud Flow rule matched',
		'external.webhook' => 'Authenticated external webhook',
		'nextcloud.board.published' => 'Persistent announcement on the board',
	];
	public const STATUSES = ['draft', 'active', 'paused', 'archived'];
	public const PRESENTATIONS = ['banner', 'modal', 'board', 'none'];
	public const PLACEMENTS = ['any', 'files', 'dashboard', 'settings', 'admin_settings'];
	public const FREQUENCIES = ['event', 'once', 'daily', 'weekly', 'custom'];
	public const CATEGORIES = ['institutional', 'sponsored'];
	// Preserve legacy themes while supporting the four native NcNoteCard types.
	public const THEMES = ['default', 'primary', 'info', 'success', 'warning', 'error', 'neutral', 'custom'];
	public const BANNER_ICONS = [
		'auto','none','info','success','warning','error','shield',
		'megaphone','bullhorn','bell','bell-ring','alert','alert-circle','alert-octagon',
		'information','information-outline','check-circle','check-decagram',
		'close-circle','close-octagon','shield-alert','shield-check','lock',
		'wrench','progress-wrench','broom','server','server-off','database',
		'cloud','cloud-alert','wifi-off','sync','update','download','upload',
		'calendar','calendar-clock','clock-alert','email','flag','star',
		'gift','party-popper','rocket-launch','new-box','lightning-bolt','help-circle',
	];

	public function __construct(
		private CampaignMapper $mapper,
		private IGroupManager $groupManager,
		private IUserManager $userManager,
		private CampaignInputValidator $inputValidator,
		private CampaignOrderPlanner $orderPlanner,
		private NativeEventSubscriptions $nativeSubscriptions,
		private \OCP\IL10N $l10n,
	) {
	}

	/**
	 * Stable keyset pagination. Historical records are never silently hidden
	 * by an arbitrary 500-row cutoff.
	 * @return \Generator<int,array>
	 */
	public function scan(): \Generator {
		foreach ($this->mapper->scanRows() as $row) {
			yield $this->castRow($row);
		}
	}

	/** Legacy admin and CLI list. Prefer pagedList() for large UI datasets. */
	public function all():array {
		$rows = iterator_to_array($this->scan(), false);
		usort($rows, static fn (array $a, array $b):int
			=> ($b['updated_at'] <=> $a['updated_at']) ?: ($b['id'] <=> $a['id']));
		return $rows;
	}

	/**
	 * Strictly public, preapproved banner projection. Never include audience IDs,
	 * group membership, creator, metrics, unpublished records or internal data.
	 */
	public function publicForGuests(int $now):array {
		$rows = $this->mapper->guestCandidates();
		$out = [];
		foreach ($rows as $row) {
			$c = $this->castRow($row);
			if ($c['starts_at'] !== null && $c['starts_at'] > $now) {
				continue;
			}
			if ($c['ends_at'] !== null && $c['ends_at'] <= $now) {
				continue;
			}
			if (!GuestBannerPolicy::supports($c)) {
				continue;
			}
			$out[] = [
				'id' => $c['id'],'version' => $c['updated_at'],
				'headline' => $c['headline'],'message' => $c['message'],
				'ctaLabel' => $c['cta_label'],
				'ctaUrl' => CtaUrl::isAllowed($c['cta_url'])?$c['cta_url']:'',
				'type' => $c['theme'],'color' => $c['banner_color'],
				'dismissible' => $c['dismissible'],
				'startsAt' => $c['starts_at'],'endsAt' => $c['ends_at'],
			];
			if (count($out) >= 5) {
				break;
			}
		}
		return $out;
	}

	public function find(int $id): ?array {
		$row = $this->mapper->byId($id);
		return $row === null ? null : $this->castRow($row);
	}

	public function activeForTrigger(string $trigger): array {
		return array_map($this->castRow(...), $this->mapper->activeForTrigger($trigger));
	}

	/**
	 * Resolve editorial translations without exposing the full locale dictionary
	 * to announcement readers. A missing locale falls back to the authored copy.
	 */
	public static function localize(array $campaign, string $locale): array {
		$locale = str_replace('-', '_', $locale);
		$map = $campaign['translated_content'] ?? [];
		if (!is_array($map) || $map === []) {
			return $campaign;
		}
		$short = substr($locale, 0, 2);
		$choice = $map[$locale] ?? $map[$short] ?? null;
		if (!is_array($choice)) {
			foreach ($map as $name => $translation) {
				if (is_string($name) && str_starts_with($name, $short . '_') && is_array($translation)) {
					$choice = $translation;
					break;
				}
			}
		}
		if (!is_array($choice)) {
			return $campaign;
		}
		foreach (['headline','message','cta_label'] as $field) {
			if (is_string($choice[$field] ?? null) && trim($choice[$field]) !== '') {
				$campaign[$field] = $choice[$field];
			}
		}
		return $campaign;
	}

	public function isEligible(array $campaign, string $uid, int $now): bool {
		if ($campaign['status'] !== 'active') {
			return false;
		}
		if ($campaign['starts_at'] !== null && $campaign['starts_at'] > $now) {
			return false;
		}
		if ($campaign['ends_at'] !== null && $campaign['ends_at'] <= $now) {
			return false;
		}
		return $this->isAudienceMember($campaign, $uid);
	}

	/** Recheck current audience even for a previously delivered, expired item. */
	public function isAudienceMember(array $campaign, string $uid):bool {
		if ($campaign['excluded_groups'] !== []) {
			$excluded = ($campaign['excluded_match'] ?? 'any') === 'all'
				? $this->inAllGroups($uid, $campaign['excluded_groups'])
				: $this->inAnyGroup($uid, $campaign['excluded_groups']);
			if ($excluded) {
				return false;
			}
		}
		return match ($campaign['audience_type']) {
			'all' => true,
			'admins' => $this->groupManager->isAdmin($uid),
			'group' => $campaign['group_match'] === 'all'
				? $this->inAllGroups($uid, $campaign['audience_groups'])
				: $this->inAnyGroup($uid, $campaign['audience_groups']),
			'users' => in_array($uid, $campaign['audience_users'], true),
			default => false,
		};
	}

	private function inAllGroups(string $uid, array $groups): bool {
		if ($groups === []) {
			return false;
		}
		foreach ($groups as $groupId) {
			if (!$this->groupManager->isInGroup($uid, $groupId)) {
				return false;
			}
		}
		return true;
	}

	private function inAnyGroup(string $uid, array $groups): bool {
		foreach ($groups as $groupId) {
			if ($this->groupManager->isInGroup($uid, $groupId)) {
				return true;
			}
		}
		return false;
	}

	public function groupOptions(): array {
		$options = [];
		foreach ($this->groupManager->search('', 150) as $group) {
			$options[] = ['id' => $group->getGID(), 'name' => $group->getDisplayName()];
		}
		usort($options, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
		return $options;
	}

	public function userOptions(string $search): array {
		$search = trim(mb_substr($search, 0, 80));
		if ($search === '') {
			return [];
		}
		$found = [];
		// Nextcloud searches UIDs and display names separately. Deduplicate by UID.
		foreach ([$this->userManager->search($search, 30), $this->userManager->searchDisplayName($search, 30)] as $users) {
			foreach ($users as $user) {
				$uid = $user->getUID();
				$found[$uid] = ['id' => $uid, 'name' => $user->getDisplayName()];
				if (count($found) >= 40) {
					break 2;
				}
			}
		}
		return array_values($found);
	}

	/**
	 * Target IDs are validated on the server; the browser never decides authorization.
	 * 'group' is accepted as a legacy single-group input for existing API consumers.
	 */
	public function validate(array $payload): array {
		return $this->inputValidator->validate($payload);
	}

	public function create(array $data, string $uid): array {
		$validated = $this->validate($data);
		$this->nativeSubscriptions->assertCapacity($validated['trigger_key'], $validated['status']);
		$now = time();
		$validated['created_by'] = $uid;
		$validated['created_at'] = $now;
		$validated['published_at'] = $validated['status'] === 'active' ? $now : 0;
		$validated['updated_at'] = $now;
		$id = $this->mapper->insert($validated);
		if (str_starts_with($validated['trigger_key'], 'php_event.') && $validated['status'] === 'active') {
			$this->nativeSubscriptions->refresh();
		}
		$created = $this->find($id);
		if ($created === null) {
			throw new \RuntimeException('Created publication could not be loaded.');
		}
		return $created;
	}

	public function update(int $id, array $data): ?array {
		$original = $this->find($id);
		if ($original === null) {
			return null;
		}
		$validated = $this->validate($data);
		$this->nativeSubscriptions->assertCapacity($validated['trigger_key'], $validated['status'], $id);
		$validated['updated_at'] = time();
		$validated['published_at'] = ($original['status'] !== 'active' && $validated['status'] === 'active')
			? time() : $original['published_at'];
		$this->mapper->update($id, $validated);
		if (str_starts_with($original['trigger_key'], 'php_event.')
			|| str_starts_with($validated['trigger_key'], 'php_event.')) {
			$this->nativeSubscriptions->refresh();
		}
		return $this->find($id);
	}

	/**
	 * Reorder non-archived campaigns; preserve audience, triggers and every
	 * other property. A transaction prevents partially persisted ordering.
	 * Keyboard buttons and drag-and-drop share this API.
	 */
	public function move(int $id, string $direction, ?int $targetId = null):?array {
		try {
			$rows = $this->orderPlanner->plan($this->all(), $id, $direction, $targetId);
		} catch (CampaignOrderException $error) {
			$message = match ($error->reason) {
				CampaignOrderException::INVALID_DIRECTION => $this->l10n->t('Invalid reordering operation.'),
				CampaignOrderException::MISSING_TARGET => $this->l10n->t('Select the destination campaign.'),
				CampaignOrderException::UNKNOWN_TARGET => $this->l10n->t('Destination campaign unavailable.'),
				default => throw $error,
			};
			throw new InvalidArgumentException($message, 0, $error);
		}
		if ($rows === null) {
			return null;
		}
		$this->mapper->persistOrder($rows);
		return $rows;
	}

	/**
	 * Irreversible deletion is intentionally separate from the archive endpoint.
	 * Only archived records can be deleted, and caller must confirm exact name.
	 * Related delivery / publication evidence is removed transactionally; the
	 * global event stream remains shared between publications and is retained.
	 */
	public function purgeArchived(int $id, string $confirmName):bool {
		$original = $this->find($id);
		if ($original === null) {
			return false;
		}
		if ($original['status'] !== 'archived') {
			throw new InvalidArgumentException($this->l10n->t('Archive the publication before deleting it permanently.'));
		}
		if ($confirmName === '' || !hash_equals($original['name'], $confirmName)) {
			throw new InvalidArgumentException($this->l10n->t('Enter the exact internal name to permanently delete the publication.'));
		}
		try {
			$this->mapper->purgeArchived($id);
		} catch (CampaignConflictException $error) {
			throw new \RuntimeException($this->l10n->t('Concurrent deletion or changed state.'), 0, $error);
		}
		if (str_starts_with($original['trigger_key'], 'php_event.')) {
			$this->nativeSubscriptions->refresh();
		}
		return true;
	}

	public function setStatus(int $id, string $status): ?array {
		if (!in_array($status, self::STATUSES, true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid campaign status.'));
		}
		$original = $this->find($id);
		if ($original === null) {
			return null;
		}
		$this->nativeSubscriptions->assertCapacity($original['trigger_key'], $status, $id);
		$this->mapper->setStatus($id, $status, time());
		if (str_starts_with($original['trigger_key'], 'php_event.')) {
			$this->nativeSubscriptions->refresh();
		}
		return $this->find($id);
	}

	private function castRow(array $row): array {
		// Legacy rows use the schema default marker for unrestricted pages.
		if (!isset($row['page_route']) || $row['page_route'] === '*') {
			$row['page_route'] = '';
		}
		$rawFilters = json_decode($row['page_filters'] ?? '{}', true);
		$row['page_filters'] = is_array($rawFilters) ? $rawFilters : [];
		$row['text_color'] = $row['text_color'] ?? '';
		$row['excluded_match'] = $row['excluded_match'] ?? 'any';
		$translated = json_decode($row['translated_content'] ?? '{}', true);
		$row['translated_content'] = is_array($translated) && !array_is_list($translated) ? $translated : [];
		foreach (['board_visible','guest_visible','comments_enabled','dismissible','notify_users','notify_activity','notify_email'] as $flag) {
			$row[$flag] = (int)($row[$flag] ?? 0) === 1;
		}
		foreach (['id', 'priority', 'created_at', 'updated_at', 'published_at', 'min_interval_minutes', 'max_total', 'max_per_day','manual_order'] as $field) {
			$row[$field] = (int)$row[$field];
		}
		foreach (['starts_at', 'ends_at'] as $field) {
			$row[$field] = ($row[$field] === null || $row[$field] === '') ? null : (int)$row[$field];
		}
		foreach (['audience_groups', 'audience_users', 'excluded_groups', 'app_target_apps', 'gateway_channels'] as $field) {
			$value = $row[$field] ?? '[]';
			$decoded = json_decode($value, true);
			$row[$field] = is_array($decoded) && array_is_list($decoded)
				? array_values(array_filter($decoded, 'is_string')) : [];
		}
		if ($row['audience_type'] === 'group' && $row['audience_groups'] === [] && $row['audience_group'] !== '') {
			$row['audience_groups'] = [$row['audience_group']];
		}
		return $row;
	}
}
