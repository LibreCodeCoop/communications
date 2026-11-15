<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Isolated Nextcloud 36 Flow integration test helper.
 * php tests/integration/fixtures/flow_integration.php create|verify|cleanup
 *
 * Does not depend on live credentials or external services.
 */
require_once '/var/www/html/lib/base.php';

use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EventStore;
use OCA\Communications\Service\FlowCampaignOperation;
use OCA\WorkflowEngine\Check\FileMimeType;
use OCA\WorkflowEngine\Entity\File as FileEntity;
use OCA\WorkflowEngine\Helper\ScopeContext;
use OCA\WorkflowEngine\Manager;
use OCP\WorkflowEngine\IManager;

$state = '/tmp/cc_flow_test_state.json';
$cmd = $argv[1] ?? '';
$container = \OC::$server;
$repo = $container->get(CampaignRepository::class);
$manager = $container->get(Manager::class);
$scope = new ScopeContext(IManager::SCOPE_ADMIN);
if ($cmd === 'create') {
	$campaign = $repo->create([
		'name' => 'Flow verified ' . bin2hex(random_bytes(4)),
		'description' => 'Temporary end-to-end test of matching Flow conditions',
		'headline' => 'Real Flow matched', 'message' => 'Flow match confirmed',
		'cta_label' => '', 'cta_url' => '', 'trigger_key' => 'nextcloud.flow.matched',
		'audience_type' => 'users', 'audience_users' => ['poc_admin'], 'audience_groups' => [],
		'presentation' => 'banner',
		'status' => 'active','priority' => 99, 'starts_at' => null,'ends_at' => null,
		'frequency' => 'once', 'placement' => 'files',
	], 'poc_admin');
	$id = (int)$campaign['id'];
	$alternative = $repo->create([
		...$campaign,
		'name' => 'Flow verified alternative ' . bin2hex(random_bytes(4)),
		'headline' => 'Flow switched successfully',
	], 'poc_admin');
	$alternativeId = (int)$alternative['id'];
	$rule = $manager->addOperation(
		FlowCampaignOperation::class,
		'Flow matching text files #' . $id,
		[['class' => FileMimeType::class, 'operator' => 'is','value' => 'text/plain']],
		(string)$id,
		$scope,
		FileEntity::class,
		['\OCP\Files::postCreate']
	);
	$stateData = ['campaignId' => $id,'alternativeId' => $alternativeId,'flowRuleId' => (int)$rule['id']];
	file_put_contents($state, json_encode($stateData, JSON_THROW_ON_ERROR));
	echo 'FLOW_CREATED ' . json_encode($stateData) . "\n";
} elseif ($cmd === 'verify') {
	$d = json_decode(file_get_contents($state), true);
	$id = (int)($argv[2] ?? $d['campaignId']);
	$store = $container->get(EventStore::class);
	$events = $store->forUser('poc_admin', time() - 3600, 300);
	$match = array_values(array_filter($events, static fn (array $e):bool
	  => $e['trigger_key'] === 'nextcloud.flow.matched' && $e['context_app'] === 'campaign_' . $id));
	$delivery = $container->get(DeliveryStore::class);
	$delivery->prepare('poc_admin', 'files', false);
	$pending = $delivery->pending('poc_admin', 'files');
	$seen = array_values(array_filter($pending, static fn (array $p):bool => $p['campaignId'] === $id));
	echo 'FLOW_VERIFIED ' . json_encode(['eventCount' => count($match),'deliveryCount' => count($seen)]) . "\n";
	exit(count($match) > 0 && count($seen) > 0 ? 0:2);
} elseif ($cmd === 'cleanup') {
	if (is_file($state)) {
		$d = json_decode(file_get_contents($state), true);
		$manager->deleteOperation((int)$d['flowRuleId'], $scope);
		$repo->setStatus((int)$d['campaignId'], 'archived');
		if (!empty($d['alternativeId'])) {
			$repo->setStatus((int)$d['alternativeId'], 'archived');
		}
		unlink($state);
		echo 'FLOW_CLEANED_UP' . "\n";
	}
} else {
	fwrite(STDERR, 'Usage: create|verify|cleanup' . "\n");
	exit(1);
}
