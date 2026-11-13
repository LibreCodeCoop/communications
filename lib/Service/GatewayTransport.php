<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\App\IAppManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Optional Two-Factor Gateway direct integration, using the standard direct-provider contract.
 *
 * Only an explicitly configured direct gateway can send messages. The
 * authentication gateway's user 2FA settings never imply publication consent.
 */
class GatewayTransport {
	public const APP = 'twofactor_gateway';
	private const SERVICE = 'OCA\\TwoFactorGateway\\Service\\GatewayDirectIntegrationService';

	/** The IDs passed to GatewayDirectIntegrationService (not 2FA method names). */
	public const LABELS = [
		'sms' => 'SMS',
		'signal' => 'Signal',
		'telegram' => 'Telegram',
		'gowhatsapp' => 'WhatsApp',
		'whatsappbusiness' => 'WhatsApp Business',
		'xmpp' => 'XMPP',
	];

	public function __construct(
		private ContainerInterface $container,
		private IAppManager $apps,
		private LoggerInterface $logger,
	) {
	}

	public function installed(): bool {
		return $this->apps->isEnabledForAnyone(self::APP);
	}

	private function service(): object {
		if (!$this->installed()) {
			throw new \RuntimeException('Two-Factor Gateway is not enabled');
		}
		$service = $this->container->get(self::SERVICE);
		if (!is_object($service) || !is_callable([$service,'ensureAvailable'])
	  || !is_callable([$service,'isGatewayComplete']) || !is_callable([$service,'send'])) {
			throw new \RuntimeException('Two-Factor Gateway direct integration is unavailable');
		}
		return $service;
	}

	public function configured(string $gateway): bool {
		if (!isset(self::LABELS[$gateway]) || !$this->installed()) {
			return false;
		}
		try {
			return $this->service()->isGatewayComplete($gateway) === true;
		} catch (\Throwable $error) {
			// Never log destination identifiers, provider credentials, or exception text.
			$this->logger->debug('Gateway capability check failed', [
				'app' => 'communications',
				'gateway' => $gateway,
				'exceptionClass' => $error::class,
			]);
			return false;
		}
	}

	/** @throws \RuntimeException */
	public function send(string $gateway, string $destination, string $body): void {
		if (!isset(self::LABELS[$gateway])) {
			throw new \InvalidArgumentException('Unknown messaging gateway');
		}
		if (!GatewayRecipientPreferences::validDestination($gateway, $destination)) {
			throw new \InvalidArgumentException('Invalid messaging destination');
		}
		if (trim($body) === '' || mb_strlen($body) > 5000) {
			throw new \InvalidArgumentException('Invalid messaging body');
		}
		$service = $this->service();
		$service->ensureAvailable($gateway);
		if ($service->isGatewayComplete($gateway) !== true) {
			throw new \RuntimeException('Messaging gateway is not configured');
		}
		try {
			$service->send($gateway, $destination, $body);
		} catch (\Throwable $error) {
			// Provider may have accepted the message before losing its response.
			throw new DeliveryOutcomeUnknownException('Gateway outcome unknown', 0, $error);
		}
	}
}
