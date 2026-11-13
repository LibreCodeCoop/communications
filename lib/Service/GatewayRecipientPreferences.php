<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\IConfig;

/**
 * Strict opt-in destinations for announcements. These are separate from
 * authentication identifiers used by Two-Factor Gateway itself.
 *
 * No settings exist until the affected user saves them. Revocation removes
 * the address from the persisted preference. Editors never read these values.
 */
final class GatewayRecipientPreferences {
	private const APP = 'communications';
	private const KEY = 'gateway_notice_consent_v1';

	public function __construct(
		private IConfig $config,
	) {
	}

	public static function validDestination(string $gateway, string $destination): bool {
		if (!isset(GatewayTransport::LABELS[$gateway]) || $destination !== trim($destination)
	  || strlen($destination) > 190) {
			return false;
		}
		return match ($gateway) {
			'sms','signal','gowhatsapp','whatsappbusiness'
	 => preg_match('/^\+[1-9][0-9]{6,14}$/D', $destination) === 1,
			'telegram'
	 => preg_match('/^[1-9][0-9]{4,19}$/D', $destination) === 1,
			'xmpp'
	 => preg_match('/^[A-Za-z0-9_.+-]{1,64}@[A-Za-z0-9.-]{3,120}$/D', $destination) === 1
	   && str_contains(substr($destination, strpos($destination, '@') + 1), '.'),
			default => false,
		};
	}

	/** @return array<string,array{destination:string,sponsored:bool}> */
	public function forUser(string $uid): array {
		$raw = $this->config->getUserValue($uid, self::APP, self::KEY, '');
		if ($raw === '') {
			return [];
		}
		$decoded = json_decode($raw, true);
		if (!is_array($decoded) || array_is_list($decoded)) {
			return [];
		}
		$result = [];
		foreach ($decoded as $gateway => $entry) {
			if (is_string($gateway) && isset(GatewayTransport::LABELS[$gateway])
	 && is_array($entry) && is_string($entry['destination'] ?? null)
	 && self::validDestination($gateway, $entry['destination'])) {
				$result[$gateway] = [
					'destination' => $entry['destination'],
					'sponsored' => ($entry['sponsored'] ?? false) === true,
				];
			}
		}
		return $result;
	}

	/** @return array<string,array{destination:string,sponsored:bool}> */
	public function replace(string $uid, array $entries): array {
		if (count($entries) > count(GatewayTransport::LABELS) || array_is_list($entries) && $entries !== []) {
			throw new \InvalidArgumentException('Invalid messaging preferences');
		}
		$saved = [];
		foreach ($entries as $gateway => $entry) {
			if (!is_string($gateway) || !isset(GatewayTransport::LABELS[$gateway])
	  || !is_array($entry) || array_is_list($entry) || array_diff(array_keys($entry), ['enabled','destination','sponsored']) !== []) {
				throw new \InvalidArgumentException('Invalid messaging gateway preference');
			}
			$enabled = $entry['enabled'] ?? false;
			$sponsored = $entry['sponsored'] ?? false;
			if (!is_bool($enabled) || !is_bool($sponsored)
	   || !is_string($entry['destination'] ?? null)) {
				throw new \InvalidArgumentException('Messaging consent must be explicit');
			}
			if (!$enabled) {
				continue;
			}
			if (!self::validDestination($gateway, $entry['destination'])) {
				throw new \InvalidArgumentException('Invalid destination for ' . $gateway);
			}
			$saved[$gateway] = [
				'destination' => $entry['destination'], 'sponsored' => $sponsored,
			];
		}
		if ($saved === []) {
			$this->config->deleteUserValue($uid, self::APP, self::KEY);
		} else {
			$this->config->setUserValue($uid, self::APP, self::KEY, json_encode($saved, JSON_THROW_ON_ERROR));
		}
		return $saved;
	}

	public function destinationFor(string $uid, string $gateway, string $category): ?string {
		$item = $this->forUser($uid)[$gateway] ?? null;
		if ($item === null || ($category === 'sponsored' && !$item['sponsored'])) {
			return null;
		}
		return $item['destination'];
	}
}
