<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;

/** An invalid reorder plan with a reason independent of the user's locale. */
final class CampaignOrderException extends InvalidArgumentException {
	public const INVALID_DIRECTION = 'invalid_direction';
	public const MISSING_TARGET = 'missing_target';
	public const UNKNOWN_TARGET = 'unknown_target';

	public function __construct(
		public readonly string $reason,
		string $message,
	) {
		parent::__construct($message);
	}
}
