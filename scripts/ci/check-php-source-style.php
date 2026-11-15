<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Project-specific checks not covered by the standard Nextcloud fixer.
 * Keep this restricted to documented continuation and PHPDoc mistakes.
 */
$root = dirname(__DIR__, 2);
$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root . '/lib', FilesystemIterator::SKIP_DOTS),
);
$problems = [];

foreach ($iterator as $file) {
	if (!$file->isFile() || $file->getExtension() !== 'php') {
		continue;
	}
	$lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
	if ($lines === false) {
		throw new RuntimeException('Could not read PHP source: ' . $file->getPathname());
	}
	$docblock = false;
	$previousIndent = null;
	foreach ($lines as $index => $line) {
		if (preg_match('/^\s*\/\*\*/', $line)) {
			$docblock = true;
		}
		$reason = null;
		if (preg_match('/^\t+ {1,3}(?:&&|\|\||\?|:|=>)/', $line)) {
			$reason = 'mixed tabs/spaces before a continuation operator';
		} elseif (preg_match('/^(\t+)(?:&&|\|\||\?|:|=>)/', $line, $match)
			&& $previousIndent !== null && strlen($match[1]) < $previousIndent) {
			$reason = 'continuation dedented relative to the preceding statement';
		} elseif ($docblock && strlen($line) > 100
			&& preg_match('/^\s*\* [^@]/', $line)
			&& !str_contains($line, 'SPDX-')) {
			$reason = 'class or method documentation exceeds 100 columns';
		}
		if ($reason !== null) {
			$relative = substr($file->getPathname(), strlen($root) + 1);
			$problems[] = sprintf('%s:%d: %s', $relative, $index + 1, $reason);
		}
		if (str_contains($line, '*/')) {
			$docblock = false;
		}
		if (trim($line) !== ''
			&& !preg_match('/^\s*(?:\/\/|\/\*|\*)/', $line)) {
			$previousIndent = strspn($line, "\t");
		}
	}
}
if ($problems !== []) {
	fwrite(STDERR, implode("\n", $problems) . "\n");
	exit(1);
}
echo "PHP continuation indentation and documentation line lengths verified.\n";
