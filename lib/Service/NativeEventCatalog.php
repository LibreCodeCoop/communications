<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;

/**
 * Index typed PHP events of enabled apps on ADMIN discovery, never on
 * user requests or while dispatching an event.
 *
 * Catalog entries are metadata only. Do not instantiate events, call their
 * getters or serialize their bodies. No untrusted class name reaches autoload.
 */
final class NativeEventCatalog {
	private const APP_ID = 'communications';
	private const INDEX_KEY = 'native_event_catalog_v2';
	private const REV_KEY = 'native_event_revision_v2';
	private const MAX_EVENTS = 500;
	private ?array $memo = null;

	public function __construct(
		private IAppManager $apps,
		private IAppConfig $config,
		private \OCP\IL10N $l10n,
	) {
	}

	public static function triggerKey(string $eventClass): string {
		return 'php_event.' . substr(hash('sha256', ltrim($eventClass, '\\')), 0, 24);
	}

	/** @return list<array{key:string,appId:string,class:string,label:string,description:string,webhook:bool,phase:string}> */
	public function all(): array {
		if ($this->memo !== null) {
			return $this->memo;
		}
		$enabled = $this->apps->getEnabledApps();
		$revisionData = ['core' => (int)@filemtime(\OC::$SERVERROOT . '/version.php')];
		foreach ($enabled as $app) {
			if ($app === self::APP_ID) {
				continue;
			}
			$info = $this->apps->getAppInfo($app);
			$revisionData[$app] = [
				'version' => is_array($info) ? (string)($info['version'] ?? '') : '',
				// Development builds may publish new typed events without changing
				// info.xml. Inspect event class bytes on ADMIN catalog requests only.
				'eventClasses' => $this->eventClassFingerprint($app),
			];
		}
		ksort($revisionData);
		$revision = hash('sha256', json_encode($revisionData, JSON_THROW_ON_ERROR));
		if ($this->config->getValueString(self::APP_ID, self::REV_KEY, '') === $revision) {
			$stored = json_decode($this->config->getValueString(self::APP_ID, self::INDEX_KEY, ''), true);
			if (is_array($stored) && array_is_list($stored)) {
				return $this->memo = $stored;
			}
		}
		$events = [];
		// Core public events are present even when a corresponding UI app is absent.
		$this->scan(\OC::$SERVERROOT . '/lib/public', 'OCP', 'core', $events, true);
		foreach ($enabled as $app) {
			if ($app === self::APP_ID || !preg_match('/^[a-z0-9_-]{2,32}$/', $app)) {
				continue;
			}
			try {
				$path = $this->apps->getAppPath($app);
				$namespace = $this->apps->getAppNamespace($app);
				foreach (['Events', 'Event'] as $dir) {
					$this->scan($path . '/lib/' . $dir, $namespace . '\\' . $dir, $app, $events, false);
				}
			} catch (\Throwable) {
				// A broken third-party app may not prevent campaign configuration.
			}
			if (count($events) >= self::MAX_EVENTS) {
				break;
			}
		}
		$events = array_values($events);
		usort($events, static fn (array $a, array $b): int
		 => strcmp($a['appId'], $b['appId']) ?: strcmp($a['class'], $b['class']));
		// Write the complete index before the revision marker so an interrupted
		// write cannot make an incomplete catalog appear current.
		$this->config->setValueString(self::APP_ID, self::INDEX_KEY, json_encode($events, JSON_THROW_ON_ERROR));
		$this->config->setValueString(self::APP_ID, self::REV_KEY, $revision);
		return $this->memo = $events;
	}

	public function byKey(string $key): ?array {
		if (!preg_match('/^php_event\.[a-f0-9]{24}$/D', $key)) {
			return null;
		}
		foreach ($this->all() as $entry) {
			if ($entry['key'] === $key) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Resolve public OCP event families to their functional Nextcloud app.
	 * A core user event remains in "core"; never infer arbitrary actor/owner data.
	 */
	/**
	 * Tracks the event class inventory (including changed webhook interfaces)
	 * independently of the app version. Metadata only; no class instantiated.
	 * This runs when an administrator opens the catalog, never in the hot path
	 * of event dispatch. Bounded to supported Event/Events folders.
	 */
	private function eventClassFingerprint(string $app): string {
		try {
			$path = $this->apps->getAppPath($app);
			$hashes = [];
			foreach (['Events','Event'] as $folder) {
				$root = $path . '/lib/' . $folder;
				if (!is_dir($root) || is_link($root)) {
					continue;
				}
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ($iterator as $file) {
					if (count($hashes) >= self::MAX_EVENTS) {
						break 2;
					}
					if (!$file->isFile() || $file->isLink()
					 || !str_ends_with($file->getFilename(), 'Event.php')) {
						continue;
					}
					$rel = $folder . '/' . substr($file->getPathname(), strlen($root) + 1);
					$digest = @hash_file('sha256', $file->getPathname());
					if ($digest !== false) {
						$hashes[$rel] = $digest;
					}
				}
			}
			ksort($hashes);
			return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
		} catch (\Throwable) {
			// Unavailable optional apps must not break the administrative editor.
			return '';
		}
	}

	private function coreApp(string $class): string {
		return match (true) {
			str_starts_with($class, 'OCP\\Files\\') => 'files',
			str_starts_with($class, 'OCP\\Share\\') => 'files_sharing',
			str_starts_with($class, 'OCP\\Comments\\') => 'comments',
			str_starts_with($class, 'OCP\\Calendar\\') => 'dav',
			default => 'core',
		};
	}

	/** @param array<string,array> $out */
	private function scan(string $directory, string $namespace, string $app, array &$out, bool $core): void {
		if (!is_dir($directory) || is_link($directory)) {
			return;
		}
		$root = realpath($directory);
		if ($root === false) {
			return;
		}
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($iterator as $file) {
			if (count($out) >= self::MAX_EVENTS) {
				return;
			}
			if (!$file->isFile() || $file->isLink()) {
				continue;
			}
			$fileName = $file->getFilename();
			if (!str_ends_with($fileName, 'Event.php')) {
				continue;
			}
			$subPath = substr($file->getPathname(), strlen($root) + 1);
			if ($subPath === '' || substr_count($subPath, '/') > 6) {
				continue;
			}
			$part = substr($subPath, 0, -4);
			if (!preg_match('~^[A-Za-z][A-Za-z0-9_]*(?:/[A-Za-z][A-Za-z0-9_]*)*$~D', $part)) {
				continue;
			}
			// A few core files ending Event.php are not typed Event subclasses.
			$class = $namespace . '\\' . str_replace('/', '\\', $part);
			try {
				if (!class_exists($class) || !is_subclass_of($class, Event::class)) {
					continue;
				}
				$reflection = new \ReflectionClass($class);
				if ($reflection->isAbstract() || $reflection->isInterface()) {
					continue;
				}
				$appId = $core ? $this->coreApp($class) : $app;
				if ($appId !== 'core' && !$this->apps->isEnabledForAnyone($appId)) {
					continue;
				}
				$short = $reflection->getShortName();
				$phase = str_starts_with($short, 'Before') ? 'before' : 'unknown';
				$webhook = is_a($class, \OCP\EventDispatcher\IWebhookCompatibleEvent::class, true);
				$description = ($phase === 'before' ? $this->l10n->t('Before the operation; does not confirm completion. ')
				  : $this->l10n->t('The timing of this event depends on the app and does not prove the operation completed. '))
				  . $this->l10n->t('Recipient: the authenticated user of this request, not necessarily the affected user. ')
				  . (NativeEventSubscriptions::eventIsMutationByName($class)
					 ? $this->l10n->t('To keep page requests fast, this trigger ignores GET, HEAD and OPTIONS. ') : '')
				  . ($webhook ? $this->l10n->t('Compatible with Webhook Listeners.') : $this->l10n->t('Internal PHP event.'));
				$key = self::triggerKey($class);
				$out[$class] = [
					'key' => $key, 'appId' => $appId, 'class' => $class,
					'label' => 'Evento PHP: ' . $short,
					'description' => $description,
					'webhook' => $webhook, 'phase' => $phase,
				];
			} catch (\Throwable) {
				// Discovery of other apps must never affect Nextcloud's normal operation.
			}
		}
	}
}
