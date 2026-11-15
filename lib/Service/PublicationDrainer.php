<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class PublicationDrainer {
	private const BATCH_SIZE = 100;
	private const MAX_ACCOUNTS = 2000;

	public function __construct(
		private CampaignRepository $campaigns,
		private BoardPublicationService $publisher,
	) {
	}

	public function drain(): int {
		$processed = 0;
		foreach ($this->campaigns->all() as $campaign) {
			if (!PublicationDuePolicy::allows($campaign, time())) {
				continue;
			}
			for ($offset = 0; $offset < self::MAX_ACCOUNTS; $offset += self::BATCH_SIZE) {
				$result = $this->publisher->broadcast((int)$campaign['id'], (int)$campaign['published_at'], $offset);
				$processed += $result['processed'];
				if (!$result['queued']) {
					break;
				}
			}
		}
		return $processed;
	}
}
