<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use InvalidArgumentException;
use OCP\IL10N;

final class CampaignInputFields {
	public function __construct(
		private array $payload,
		private IL10N $l10n,
	) {
	}

	public function text(string $key, int $max, bool $required = false): string {
		$value = $this->payload[$key] ?? '';
		if (!is_string($value)) {
			// TRANSLATORS Prefix followed by a dynamic field name.
			throw new InvalidArgumentException($this->l10n->t('Invalid field: ') . $key);
		}
		$value = trim($value);
		if (($required && $value === '') || mb_strlen($value) > $max) {
			// TRANSLATORS Prefix followed by a field name in a publication validation error.
			$fieldPrefix = $this->l10n->t('Review the field ');
			// TRANSLATORS Prefix before a maximum character count in a validation error; keep spaces.
			$maximumPrefix = $this->l10n->t(' (maximum ');
			// TRANSLATORS Suffix following the maximum character count in a validation error.
			$maximumSuffix = $this->l10n->t(' characters).');
			throw new InvalidArgumentException($fieldPrefix . $key . $maximumPrefix . $max . $maximumSuffix);
		}
		return $value;
	}

	public function number(string $key, int $max): int {
		$value = $this->payload[$key] ?? 0;
		if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0 || (int)$value > $max) {
			// TRANSLATORS Prefix followed by a dynamic numeric field name.
			throw new InvalidArgumentException($this->l10n->t('Invalid number: ') . $key);
		}
		return (int)$value;
	}

	public function timestamp(string $key): ?int {
		$value = $this->payload[$key] ?? null;
		if ($value === null || $value === '') {
			return null;
		}
		if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) {
			// TRANSLATORS Prefix followed by a dynamic date field name.
			throw new InvalidArgumentException($this->l10n->t('Invalid date: ') . $key);
		}
		return (int)$value;
	}
}
