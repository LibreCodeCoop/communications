<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Fake official direct-integration service, no third-party messaging.
 */

require_once '/var/www/html/lib/base.php';

use OCA\Communications\Service\GatewayTransport;
use Psr\Container\ContainerInterface;

class FakeDirectGatewayV26 {
	public array $calls = [];
	public function ensureAvailable(string $id):void {
		$this->calls[] = ['ensure',$id];
	}
	public function isGatewayComplete(string $id):bool {
		$this->calls[] = ['complete',$id];
		return $id === 'telegram';
	}
	public function send(string $id, string $dest, string $body):void {
		$this->calls[] = ['send',$id,$dest,$body];
	}
}
class FakeContainerV26 implements ContainerInterface {
	public function __construct(
		private object $service,
	) {
	}
	public function get(string $id):mixed {
		if ($id !== 'OCA\TwoFactorGateway\Service\GatewayDirectIntegrationService') {
			throw new RuntimeException('Unrecognized direct service');
		}
		return $this->service;
	}
	public function has(string $id):bool {
		return true;
	}
}
class EnabledGatewayV26 extends GatewayTransport {
	public function installed():bool {
		return true;
	}
}
$fake = new FakeDirectGatewayV26();
$transport = new EnabledGatewayV26(
	new FakeContainerV26($fake),
	\OC::$server->get(\OCP\App\IAppManager::class),
	\OC::$server->get(\Psr\Log\LoggerInterface::class)
);
if (!$transport->configured('telegram') || $transport->configured('signal')) {
	throw new RuntimeException('Provider completeness check failed');
}
$transport->send('telegram', '123456789', 'Aviso de laboratório');
$called = array_values(array_filter($fake->calls, static fn (array $c):bool => $c[0] === 'send'));
if ($called !== [['send','telegram','123456789','Aviso de laboratório']]) {
	throw new RuntimeException('Unexpected direct send contract');
}
$invalid = false;
try {
	$transport->send('telegram', '-100123', 'Invalid destination');
} catch (InvalidArgumentException) {
	$invalid = true;
}
if (!$invalid) {
	throw new RuntimeException('Unsafe destination passed');
}
$notReady = false;
try {
	$transport->send('signal', '+15555550123', 'Blocked transport');
} catch (RuntimeException) {
	$notReady = true;
}
if (!$notReady) {
	throw new RuntimeException('Unconfigured gateway sent a message');
}
echo json_encode(['providerCompleteness' => true,'sendContract' => true,
	'invalidRecipientRejected' => true,'unconfiguredProviderRejected' => true,
	'calls' => $fake->calls], JSON_THROW_ON_ERROR);
