<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\IAppConfig;
use OCP\IGroupManager;

/** Grant campaign editor rights to selected Nextcloud groups, not to everyone. */
class EditorAccess {
	private const APP = 'communications';
	private const KEY = 'editor_groups_v1';
	public function __construct(
		private IAppConfig $config,
		private IGroupManager $groups,
		private \OCP\IL10N $l10n,
	) {
	}
	public function isAdmin(?string $uid):bool {
		return $uid !== null && $this->groups->isAdmin($uid);
	}
	/** @return list<string> */
	public function groups():array {
		$value = json_decode($this->config->getValueString(self::APP, self::KEY, '[]'), true);
		if (!is_array($value) || !array_is_list($value)) {
			return [];
		}
		return array_values(array_filter($value, fn ($id):bool
			=> is_string($id) && preg_match('/^[A-Za-z0-9_.:@-]{1,64}$/D', $id) === 1
			&& $this->groups->get($id) !== null));
	}
	public function canEdit(?string $uid):bool {
		if ($uid === null) {
			return false;
		}
		if ($this->groups->isAdmin($uid)) {
			return true;
		}
		foreach ($this->groups() as $gid) {
			if ($this->groups->isInGroup($uid, $gid)) {
				return true;
			}
		}
		return false;
	}
	/** @param list<string> $items */
	public function configure(?string $actor, array $items):array {
		if (!$this->isAdmin($actor)) {
			throw new InvalidArgumentException($this->l10n->t('Only administrators can delegate editing.'));
		}
		if (!array_is_list($items) || count($items) > 20) {
			throw new InvalidArgumentException($this->l10n->t('Select at most twenty editorial groups.'));
		}
		$selected = [];
		foreach ($items as $id) {
			if (!is_string($id) || !preg_match('/^[A-Za-z0-9_.:@-]{1,64}$/D', $id)
				|| $this->groups->get($id) === null) {
				throw new InvalidArgumentException($this->l10n->t('Editorial group not found.'));
			}
			$selected[$id] = $id;
		}
		$items = array_values($selected);
		$this->config->setValueString(self::APP, self::KEY, json_encode($items, JSON_THROW_ON_ERROR));
		return $items;
	}
}
