<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Activity;

use OCP\Activity\ActivitySettings;

final class BoardSetting extends ActivitySettings {
 public function __construct(private \OCP\IL10N $l10n) {}
 public function getIdentifier():string { return 'communications'; }
 public function getName():string { return $this->l10n->t('Announcements published on the board'); }
 public function getGroupIdentifier():string { return 'other'; }
 public function getGroupName():string { return $this->l10n->t('Other activities'); }
 public function getPriority():int { return 70; }
 public function canChangeStream():bool { return false; }
 public function isDefaultEnabledStream():bool { return true; }
 public function canChangeNotification():bool { return false; }
 public function isDefaultEnabledNotification():bool { return false; }
 public function canChangeMail():bool { return false; }
 public function isDefaultEnabledMail():bool { return false; }
}
