<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\IL10N;

/**
 * Computes a publication order without writing to the database.
 * Persistence remains transactional in the repository.
 */
final class CampaignOrderPlanner {
	public function __construct(
		private IL10N $l10n,
	) {
	}

	public function plan(array $campaigns, int $id, string $direction, ?int $targetId = null): ?array {
		if (!in_array($direction, ['up','down','before','after'], true)) {
			throw new InvalidArgumentException($this->l10n->t('Invalid reordering operation.'));
		}
		$rows = array_values(array_filter($campaigns,
			static fn (array $c):bool => $c['status'] !== 'archived'));
		usort($rows, self::compare(...));
		$index = null;
		foreach ($rows as $i => $c) {
			if ((int)$c['id'] === $id) {
				$index = $i;
				break;
			}
		}
		if ($index === null) {
			return null;
		}
		if ($direction === 'up' || $direction === 'down') {
			$neighbor = $index + ($direction === 'up'?-1:1);
			if ($neighbor < 0 || $neighbor >= count($rows)) {
				return $rows;
			}
			[$rows[$index],$rows[$neighbor]] = [$rows[$neighbor],$rows[$index]];
		} else {
			if ($targetId === null || $targetId < 1) {
				throw new InvalidArgumentException($this->l10n->t('Select the destination campaign.'));
			}
			if ($targetId === $id) {
				return $rows;
			}
			$current = $rows[$index];
			array_splice($rows, $index, 1);
			$position = null;
			foreach ($rows as $i => $row) {
				if ((int)$row['id'] === $targetId) {
					$position = $i;
					break;
				}
			}
			if ($position === null) {
				throw new InvalidArgumentException($this->l10n->t('Destination campaign unavailable.'));
			}
			array_splice($rows, $position + ($direction === 'after'?1:0), 0, [$current]);
		}
		return $rows;
	}

	private static function compare(array $a, array $b): int {
		$order = $a['manual_order'] <=> $b['manual_order'];
		if ($order !== 0) {
			return $order;
		}
		$priority = $b['priority'] <=> $a['priority'];
		return $priority !== 0 ? $priority : ($b['id'] <=> $a['id']);
	}
}
