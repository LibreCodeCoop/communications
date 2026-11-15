<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Validate business rules without creating publications or using outbound channels.
define('OC_CONSOLE', 1);
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';

use OCA\Communications\Service\CampaignRepository;

$validator = \OC::$server->get(CampaignRepository::class);
$draft = [
	'name' => 'No-side-effects validation fixture',
	'headline' => 'Validation fixture',
	'message' => 'Only validate the contract',
	'trigger_key' => 'nextcloud.board.published',
	'status' => 'draft',
	'presentation' => 'none',
	'board_visible' => true,
	'audience_type' => 'all',
];
$valid = $validator->validate(array_replace($draft, [
	'presentation' => 'banner',
	'board_visible' => false,
	'notify_email' => true,
	'category' => 'sponsored',
	'max_per_day' => 0,
	'banner_color' => '#abc',
	'manual_order' => -10,
	'translated_content' => ['en_US' => ['headline' => 'Title']],
]));

if ($valid['max_per_day'] !== 2 || $valid['banner_color'] !== '#AABBCC'
	|| $valid['manual_order'] !== -10 || $valid['notify_email'] !== 1
	|| $valid['translated_content'] !== '{"en_US":{"headline":"Title"}}') {
	throw new RuntimeException('Valid input does not normalize to the expected business contract');
}

$invalid = [
	'empty audience' => ['audience_type' => 'users', 'audience_users' => []],
	'unknown account' => ['audience_type' => 'users', 'audience_users' => ['__communications_fixture_missing_user__']],
	'unknown group' => ['audience_type' => 'group', 'audience_groups' => ['__communications_fixture_missing_group__']],
	'no destination' => ['board_visible' => false],
	'missing scheduled start' => ['trigger_key' => 'nextcloud.time.window'],
	'end precedes start' => ['starts_at' => 200, 'ends_at' => 100],
	'empty application allowlist' => ['presentation' => 'banner', 'app_target_mode' => 'only', 'app_target_apps' => []],
	'navigation without app' => ['trigger_key' => 'nextcloud.page.opened', 'presentation' => 'banner', 'placement' => 'any'],
	'invalid public audience' => ['presentation' => 'banner', 'board_visible' => false, 'guest_visible' => true, 'audience_type' => 'admins'],
	'duplicate gateways' => ['gateway_channels' => ['telegram', 'telegram']],
	'unapproved translation field' => ['translated_content' => ['pt_BR' => ['css' => 'unsafe']]],
	'negative frequency cap' => ['max_total' => -1],
];
foreach ($invalid as $case => $changes) {
	try {
		$validator->validate(array_replace($draft, $changes));
	} catch (InvalidArgumentException) {
		continue;
	}
	throw new RuntimeException('Invalid publication business rule was accepted: ' . $case);
}
echo sprintf('Publication validation contract passed: valid sponsored normalization and %d invalid configurations.', count($invalid)) . PHP_EOL;
