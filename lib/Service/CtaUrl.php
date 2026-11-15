<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/** Render only safe protocols from editor records; reject dangerous delimiters. */
final class CtaUrl {
	public static function isAllowed(string $url):bool {
		if ($url === '' || strlen($url) > 1900
			|| preg_match('/[\x00-\x20\x7f\x22\x27<>\x60\\\\]/', $url)) {
			return false;
		}
		if (str_starts_with($url, '/')) {
			return !str_starts_with($url, '//');
		}
		if (preg_match('~^https?://~i', $url)) {
			$parsed = parse_url($url);
			return is_array($parsed) && !empty($parsed['host']) && !isset($parsed['user'])
				&& !isset($parsed['pass']) && filter_var($url, FILTER_VALIDATE_URL) !== false;
		}
		if (preg_match('/^mailto:[^@\s]+@[^@\s]+$/i', $url)) {
			return true;
		}
		if (preg_match('/^tel:\+?[0-9().-]{3,30}$/i', $url)) {
			return true;
		}
		return false;
	}
}
