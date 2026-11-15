<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Disposable Nextcloud installation only: discovering events may update its appconfig cache.
define('OC_CONSOLE', 1);
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';

use OCA\Communications\Service\NativeEventCatalog;

$catalog = \OC::$server->get(NativeEventCatalog::class);
$events = $catalog->all();
if (count($events) < 1 || count($events) > 500) {
	throw new RuntimeException('Native event catalog was empty or exceeded its discovery limit');
}
$seen = [];
foreach ($events as $event) {
	$class = $event['class'] ?? null;
	$key = $event['key'] ?? null;
	if (!is_string($class) || !is_string($key)
		|| !hash_equals(NativeEventCatalog::triggerKey($class), $key)
		|| isset($seen[$class]) || !is_string($event['appId'] ?? null)
		|| $catalog->byKey($key) !== $event) {
		throw new RuntimeException('Native event discovery returned an invalid or nonunique descriptor');
	}
	$seen[$class] = true;
}
if ($catalog->all() !== $events) {
	throw new RuntimeException('The event catalog is not stable within one request');
}
echo sprintf('Native event discovery contract passed: %d typed events with stable keys.', count($events)) . PHP_EOL;
