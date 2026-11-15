<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Read-only contract test; run only in a disposable Nextcloud instance.
define('OC_CONSOLE', 1);
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';

use OCA\Communications\Service\CampaignRepository;

$campaigns = \OC::$server->get(CampaignRepository::class);
$draft = [
	'name' => 'Canonical contract test',
	'headline' => 'Canonical contract test',
	'message' => 'No external messages are delivered',
	'trigger_key' => 'nextcloud.board.published',
	'status' => 'draft',
	'presentation' => 'none',
	'board_visible' => true,
	'audience_type' => 'all',
];
$validated = $campaigns->validate($draft);
if ($validated['app_target_mode'] !== 'area' || $validated['theme'] !== 'info'
	|| $validated['page_route'] !== '' || isset($validated['audience_group'])) {
	throw new RuntimeException('New publications do not use the initial canonical contract');
}

$invalid = [
	'retired presentation' => ['presentation' => 'board'],
	'retired application selection mode' => ['app_target_mode' => 'legacy'],
	'retired theme' => ['theme' => 'default'],
	'singular group input' => ['audience_type' => 'group', 'audience_group' => 'admin'],
];
foreach ($invalid as $case => $overrides) {
	try {
		$campaigns->validate(array_replace($draft, $overrides));
	} catch (InvalidArgumentException) {
		continue;
	}
	throw new RuntimeException('Obsolete input accepted: ' . $case);
}

$router = \OC::$server->get(\OC\Route\Router::class);
$router->loadRoutes('communications');
$routes = $router->getRouteCollection()->all();
foreach (['communications.campaign.pending', 'communications.campaign.claim'] as $name) {
	if (isset($routes[$name])) {
		throw new RuntimeException('Obsolete endpoint remains exposed: ' . $name);
	}
}
if (!isset($routes['communications.campaign.claimbatch'])) {
	throw new RuntimeException('Current batch-claim endpoint is missing');
}

echo 'Canonical pre-release contract passed: one current schema, 4 invalid forms and 2 retired routes rejected.' . PHP_EOL;
