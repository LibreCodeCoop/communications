<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\IDBConnection;

/**
 * Compiled subscriptions are refreshed on CAMPAIGN WRITES. Normal Nextcloud
 * requests read one cached appconfig value, never scan files or query campaign
 * tables to determine which event listeners to register.
 */
final class NativeEventSubscriptions {
	private const APP_ID = 'communications';
	private const CONFIG_KEY = 'native_active_event_subscriptions_v1';
	private const MAX_SUBSCRIPTIONS = 80;
	private ?array $current = null;

	public function __construct(
		private IAppConfig $config,
		private IAppManager $apps,
		private IDBConnection $db,
		private NativeEventCatalog $catalog,
		private \OCP\IL10N $l10n,
	) {
	}

	/**
	 * Parse the already-compiled app configuration in early bootstrap. This
	 * static fast path has no DI, app-manager, DB or filesystem dependencies.
	 * A potentially disabled app is excluded later inside the event handler.
	 *
	 */
	public static function eventIsMutationByName(string $class): bool {
		// Conservative opt-out of safe HTTP methods for events explicitly named
		// after a state-changing operation. This is an optimization, not a claim
		// about every third-party business event or controller implementation.
		$short = substr($class, strrpos($class, '\\') + 1);
		return preg_match('/(?:Created|Updated|Written|Deleted|Renamed|Moved|Signed|Canceled|Cancelled|Approved|Rejected|Added|Removed|Accepted|Assigned|Unassigned|Changed|Completed)Event$/D', $short) === 1;
	}

	/** @return list<string> */
	public static function bootstrapClasses(string $json, string $httpMethod = ''): array {
		if ($json === '' || $json === '[]' || $json === '{}') {
			return [];
		}
		$decoded = json_decode($json, true);
		if (!is_array($decoded) || array_is_list($decoded)) {
			return [];
		}
		$classes = [];
		foreach ($decoded as $class => $meta) {
			if (!is_string($class) || !is_array($meta)
	   || !preg_match('/^(?:OCP|OCA)\\\\[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*Event$/D', $class)
	   || !is_string($meta['key'] ?? null)
	   || !hash_equals(NativeEventCatalog::triggerKey($class), $meta['key'])) {
				continue;
			}
			if (in_array(strtoupper($httpMethod), ['GET', 'HEAD', 'OPTIONS'], true)
	   && self::eventIsMutationByName($class)) {
				continue;
			}
			$classes[] = $class;
			if (count($classes) >= self::MAX_SUBSCRIPTIONS) {
				break;
			}
		}
		return $classes;
	}

	/** @return array<string,array{key:string,appId:string,class:string}> */
	public function active(): array {
		if ($this->current !== null) {
			return $this->current;
		}
		$raw = json_decode($this->config->getValueString(self::APP_ID, self::CONFIG_KEY, '{}'), true);
		if (!is_array($raw) || array_is_list($raw)) {
			return $this->current = [];
		}
		$active = [];
		foreach ($raw as $class => $meta) {
			if (!is_string($class) || !is_array($meta) || !is_string($meta['key'] ?? null)
	   || !is_string($meta['appId'] ?? null)
	   || !hash_equals(NativeEventCatalog::triggerKey($class), $meta['key'])
	   || !preg_match('/^[a-z0-9_-]{2,32}$/D', $meta['appId'])) {
				continue;
			}
			if ($meta['appId'] !== 'core' && !$this->apps->isEnabledForAnyone($meta['appId'])) {
				continue;
			}
			$active[$class] = ['key' => $meta['key'], 'appId' => $meta['appId'], 'class' => $class];
			if (count($active) >= self::MAX_SUBSCRIPTIONS) {
				break;
			}
		}
		return $this->current = $active;
	}

	public function forClass(string $class): ?array {
		return $this->active()[$class] ?? null;
	}

	/** Reject an excessive number of active event classes BEFORE DB mutation. */
	public function assertCapacity(string $trigger, string $status, ?int $excludeCampaignId = null): void {
		if ($status !== 'active' || !str_starts_with($trigger, 'php_event.')) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select('trigger_key')->from('cc_campaigns')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->like('trigger_key', $qb->createNamedParameter('php_event.%')))
			->groupBy('trigger_key');
		if ($excludeCampaignId !== null) {
			$qb->andWhere($qb->expr()->neq('id', $qb->createNamedParameter($excludeCampaignId)));
		}
		$result = $qb->executeQuery();
		$keys = $result->fetchFirstColumn();
		$result->closeCursor();
		if (!in_array($trigger, $keys, true) && count($keys) >= self::MAX_SUBSCRIPTIONS) {
			throw new \InvalidArgumentException($this->l10n->t('The limit of 80 active event types has been reached. Disable another campaign before activating a new source.'));
		}
	}

	/**
	 * Rebuild after a create/update/pause/archive. Querying occurs only on
	 * management writes; disabled applications are never subscribed to.
	 */
	public function refresh(): void {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('trigger_key')->from('cc_campaigns')
			->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
			->andWhere($qb->expr()->like('trigger_key', $qb->createNamedParameter('php_event.%')))
			->groupBy('trigger_key')
			->setMaxResults(self::MAX_SUBSCRIPTIONS + 1)
			->executeQuery();
		$keys = $result->fetchFirstColumn();
		$result->closeCursor();
		if (count($keys) > self::MAX_SUBSCRIPTIONS) {
			throw new \RuntimeException('Too many active dynamic event classes.');
		}
		$entries = [];
		foreach ($keys as $key) {
			if (!is_string($key)) {
				continue;
			}
			$source = $this->catalog->byKey($key);
			if ($source === null) {
				continue;
			}
			$entries[$source['class']] = [
				'key' => $source['key'], 'appId' => $source['appId'], 'class' => $source['class']
			];
		}
		$this->config->setValueString(self::APP_ID, self::CONFIG_KEY, json_encode($entries, JSON_THROW_ON_ERROR));
		$this->current = $entries;
	}
}
