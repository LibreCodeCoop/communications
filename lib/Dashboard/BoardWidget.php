<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Dashboard;

use OCA\Communications\Service\BoardService;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\IL10N;
use OCP\IURLGenerator;

class BoardWidget implements IAPIWidgetV2, IIconWidget, IButtonWidget {
	public function __construct(
		private BoardService $board,
		private IURLGenerator $urls,
		private IL10N $l10n,
	) {
	}
	public function getId():string {
		return 'communications-board';
	}
	public function getTitle():string {
		// TRANSLATORS Short label for an option or field in the Nextcloud dashboard widget.
		return $this->l10n->t('Announcement board');
	}
	public function getOrder():int {
		return 15;
	}
	public function getIconClass():string {
		return 'icon-sound';
	}
	public function getIconUrl():string {
		return $this->urls->getAbsoluteURL($this->urls->imagePath('communications', 'app.svg'));
	}
	public function getUrl():?string {
		return $this->urls->linkToRouteAbsolute('communications.page.board');
	}
	public function load():void {
	}
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7):WidgetItems {
		$items = $this->board->list($userId, 1)['announcements'];
		$result = [];
		foreach (array_slice($items, 0, min(10, max(1, $limit))) as $item) {
			if ($since !== null && $item['publishedAt'] <= (int)$since) {
				continue;
			}
			$summary = preg_replace('/[\r\n\t#*_\[\]]+/', ' ', strip_tags($item['message']));
			$result[] = new WidgetItem(
				$item['headline'],
				mb_substr(trim($summary), 0, 140),
				$this->urls->linkToRouteAbsolute('communications.page.board', ['id' => $item['id']]),
				$this->urls->getAbsoluteURL($this->urls->imagePath('communications', 'app.svg')),
				(string)$item['publishedAt']
			);
		}
		// TRANSLATORS Empty state or unavailable-condition message in the Nextcloud dashboard widget.
		return new WidgetItems($result, $result === [] ? $this->l10n->t('No announcements are available for you.') : '');
	}
	public function getWidgetButtons(string $userId):array {
		return [new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->urls->linkToRouteAbsolute('communications.page.board'),
			// TRANSLATORS Action or instruction in the Nextcloud dashboard widget.
			$this->l10n->t('Open announcement board')
		)];
	}
}
