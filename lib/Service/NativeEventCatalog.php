<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCP\App\AppPathNotFoundException;
use OCP\App\IAppManager;
use OCP\EventDispatcher\Event;
use OCP\IAppConfig;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

/** Discover only typed event-class metadata on administrator catalog requests. */
final class NativeEventCatalog {
	private const APP_ID = 'communications';
	private const INDEX_KEY = 'native_event_catalog_v2';
	private const REV_KEY = 'native_event_revision_v2';
	private const MAX_EVENTS = 500;
	private ?array $memo = null;

	public function __construct(
		private IAppManager $apps,
		private IAppConfig $config,
		private IL10N $l10n,
		private LoggerInterface $logger,
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
		$revision = $this->revision($enabled);
		$stored = $this->storedIndex($revision);
		if ($stored !== null) {
			return $this->memo = $stored;
		}
		$events = $this->discover($enabled);
		$this->config->setValueString(self::APP_ID, self::INDEX_KEY, json_encode($events, JSON_THROW_ON_ERROR));
		$this->config->setValueString(self::APP_ID, self::REV_KEY, $revision);
		return $this->memo = $events;
	}

	private function revision(array $enabled): string {
		$data = ['core' => (int)@filemtime(\OC::$SERVERROOT . '/version.php')];
		foreach ($enabled as $app) {
			if ($app === self::APP_ID) {
				continue;
			}
			$info = $this->apps->getAppInfo($app);
			$data[$app] = [
				'version' => is_array($info) ? (string)($info['version'] ?? '') : '',
				'eventClasses' => $this->eventClassFingerprint($app),
			];
		}
		ksort($data);
		return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
	}

	private function storedIndex(string $revision): ?array {
		if ($this->config->getValueString(self::APP_ID, self::REV_KEY, '') !== $revision) {
			return null;
		}
		$stored = json_decode($this->config->getValueString(self::APP_ID, self::INDEX_KEY, ''), true);
		return is_array($stored) && array_is_list($stored) ? $stored : null;
	}

	private function discover(array $enabled): array {
		$events = [];
		$this->scan(\OC::$SERVERROOT . '/lib/public', 'OCP', 'core', $events, true);
		foreach ($enabled as $app) {
			if ($app === self::APP_ID || !preg_match('/^[a-z0-9_-]{2,32}$/', $app)) {
				continue;
			}
			try {
				$path = $this->apps->getAppPath($app);
				$namespace = $this->apps->getAppNamespace($app);
				foreach (['Events', 'Event'] as $folder) {
					$this->scan($path . '/lib/' . $folder, $namespace . '\\' . $folder, $app, $events, false);
				}
			} catch (AppPathNotFoundException|\UnexpectedValueException $error) {
				$this->logger->debug('Optional app event discovery failed', [
					'appId' => $app, 'exceptionClass' => $error::class,
				]);
			}
			if (count($events) >= self::MAX_EVENTS) {
				break;
			}
		}
		$events = array_values($events);
		usort($events, self::compareEvents(...));
		return $events;
	}

	private static function compareEvents(array $a, array $b): int {
		return strcmp($a['appId'], $b['appId']) ?: strcmp($a['class'], $b['class']);
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

	private function eventClassFingerprint(string $app): string {
		$hashes = [];
		try {
			$path = $this->apps->getAppPath($app);
			foreach (['Events', 'Event'] as $folder) {
				$root = $path . '/lib/' . $folder;
				if (!is_dir($root) || is_link($root)) {
					continue;
				}
				$iterator = new \RecursiveIteratorIterator(
					new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
					\RecursiveIteratorIterator::LEAVES_ONLY,
				);
				foreach ($iterator as $file) {
					if (count($hashes) >= self::MAX_EVENTS) {
						break 2;
					}
					if (!$file->isFile() || $file->isLink() || !str_ends_with($file->getFilename(), 'Event.php')) {
						continue;
					}
					$relative = $folder . '/' . substr($file->getPathname(), strlen($root) + 1);
					$digest = @hash_file('sha256', $file->getPathname());
					if ($digest !== false) {
						$hashes[$relative] = $digest;
					}
				}
			}
		} catch (AppPathNotFoundException|\UnexpectedValueException $error) {
			$this->logger->debug('Optional app event index unavailable', [
				'appId' => $app, 'exceptionClass' => $error::class,
			]);
			return '';
		}
		ksort($hashes);
		return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
	}

	/** Return the Nextcloud app that owns this OCP event family. */
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
			$relative = substr($file->getPathname(), strlen($root) + 1);
			$class = NativeEventClassPolicy::fromRelativePath($relative, $namespace);
			if ($class === null) {
				continue;
			}
			$entry = $this->describe($class, $app, $core);
			if ($entry !== null) {
				$out[$class] = $entry;
			}
		}
	}

	private function describe(string $class, string $app, bool $core): ?array {
		try {
			if (!class_exists($class) || !is_subclass_of($class, Event::class)) {
				return null;
			}
			$reflection = new \ReflectionClass($class);
			if ($reflection->isAbstract() || $reflection->isInterface()) {
				return null;
			}
			$appId = $core ? $this->coreApp($class) : $app;
			if ($appId !== 'core' && !$this->apps->isEnabledForAnyone($appId)) {
				return null;
			}
			$short = $reflection->getShortName();
			$phase = str_starts_with($short, 'Before') ? 'before' : 'unknown';
			$webhook = is_a($class, \OCP\EventDispatcher\IWebhookCompatibleEvent::class, true);
			return [
				'key' => self::triggerKey($class), 'appId' => $appId, 'class' => $class,
				// TRANSLATORS Prefix before the short class name of a discovered PHP event.
				'label' => $this->l10n->t('PHP event: ') . $short,
				'description' => $this->description($class, $phase, $webhook),
				'webhook' => $webhook, 'phase' => $phase,
			];
		} catch (\ReflectionException|\Error $error) {
			$this->logger->debug('External event metadata was skipped', [
				'appId' => $app, 'exceptionClass' => $error::class,
			]);
			return null;
		}
	}

	private function description(string $class, string $phase, bool $webhook): string {
		// TRANSLATORS Prefix followed by a dynamic value in the Nextcloud native-event catalog; preserve its trailing space.
		$timing = $phase === 'before'
			? $this->l10n->t('Before the operation; does not confirm completion. ')
			// TRANSLATORS Prefix followed by a dynamic value in the Nextcloud native-event catalog; preserve its trailing space.
			: $this->l10n->t('The timing of this event depends on the app and does not prove the operation completed. ');
		// TRANSLATORS Prefix followed by a dynamic value in the Nextcloud native-event catalog; preserve its trailing space.
		$recipient = $this->l10n->t('Recipient: the authenticated user of this request, not necessarily the affected user. ');
		$readMethods = '';
		if (NativeEventSubscriptions::eventIsMutationByName($class)) {
			// TRANSLATORS Prefix followed by a dynamic value in the Nextcloud native-event catalog; preserve its trailing space.
			$readMethods = $this->l10n->t('To keep page requests fast, this trigger ignores GET, HEAD and OPTIONS. ');
		}
		// TRANSLATORS Short label for an option or field in the Nextcloud native-event catalog.
		$kind = $webhook
			? $this->l10n->t('Compatible with Webhook Listeners.')
			// TRANSLATORS Short label for an option or field in the Nextcloud native-event catalog.
			: $this->l10n->t('Internal PHP event.');
		return $timing . $recipient . $readMethods . $kind;
	}
}
