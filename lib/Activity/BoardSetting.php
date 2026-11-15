<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Activity;

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

final class BoardSetting extends ActivitySettings {
	public function __construct(
		private IL10N $l10n,
	) {
	}
	public function getIdentifier():string {
		return 'communications';
	}
	public function getName():string {
		// TRANSLATORS Activity setting for announcements published on the Communications board.
		return $this->l10n->t('Announcements published on the board');
	}
	public function getGroupIdentifier():string {
		return 'other';
	}
	public function getGroupName():string {
		// TRANSLATORS Name of the miscellaneous category in the Nextcloud Activity app.
		return $this->l10n->t('Other activities');
	}
	public function getPriority():int {
		return 70;
	}
	public function canChangeStream():bool {
		return false;
	}
	public function isDefaultEnabledStream():bool {
		return true;
	}
	public function canChangeNotification():bool {
		return false;
	}
	public function isDefaultEnabledNotification():bool {
		return false;
	}
	public function canChangeMail():bool {
		return false;
	}
	public function isDefaultEnabledMail():bool {
		return false;
	}
}
