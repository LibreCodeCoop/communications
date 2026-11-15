<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/**
 * Computes a publication order without writing to the database.
 * Persistence remains transactional in the repository.
 */
final class CampaignOrderPlanner {

	public function plan(array $campaigns, int $id, string $direction, ?int $targetId = null): ?array {
		if (!in_array($direction, ['up', 'down', 'before', 'after'], true)) {
			throw new CampaignOrderException(CampaignOrderException::INVALID_DIRECTION, 'Invalid reordering operation.');
		}
		$rows = array_values(array_filter($campaigns,
			static fn (array $campaign): bool => $campaign['status'] !== 'archived'));
		usort($rows, self::compare(...));
		$position = self::indexOf($rows, $id);
		if ($position === null) {
			return null;
		}
		return in_array($direction, ['up', 'down'], true)
			? self::moveAdjacent($rows, $position, $direction)
			: self::moveRelative($rows, $position, $id, $direction, $targetId);
	}

	private static function indexOf(array $rows, int $id): ?int {
		foreach ($rows as $index => $row) {
			if ((int)$row['id'] === $id) {
				return $index;
			}
		}
		return null;
	}

	private static function moveAdjacent(array $rows, int $position, string $direction): array {
		$neighbor = $position + ($direction === 'up' ? -1 : 1);
		if ($neighbor >= 0 && $neighbor < count($rows)) {
			[$rows[$position], $rows[$neighbor]] = [$rows[$neighbor], $rows[$position]];
		}
		return $rows;
	}

	private static function moveRelative(
		array $rows, int $position, int $id, string $direction, ?int $targetId,
	): array {
		if ($targetId === null || $targetId < 1) {
			throw new CampaignOrderException(CampaignOrderException::MISSING_TARGET, 'Select the destination campaign.');
		}
		if ($id === $targetId) {
			return $rows;
		}
		$campaign = $rows[$position];
		array_splice($rows, $position, 1);
		$target = self::indexOf($rows, $targetId);
		if ($target === null) {
			throw new CampaignOrderException(CampaignOrderException::UNKNOWN_TARGET, 'Destination campaign unavailable.');
		}
		array_splice($rows, $target + ($direction === 'after' ? 1 : 0), 0, [$campaign]);
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
