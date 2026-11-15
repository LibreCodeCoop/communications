<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

final class NativeEventClassPolicy {
	public static function fromRelativePath(string $path, string $namespace): ?string {
		if (!str_ends_with($path, 'Event.php') || substr_count($path, '/') > 6) {
			return null;
		}
		$part = substr($path, 0, -4);
		if (!preg_match('~^[A-Za-z][A-Za-z0-9_]*(?:/[A-Za-z][A-Za-z0-9_]*)*$~D', $part)) {
			return null;
		}
		return $namespace . '\\' . str_replace('/', '\\', $part);
	}
}
