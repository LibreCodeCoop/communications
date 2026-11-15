<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OCA\Communications\Event\RegisterTriggerCapabilitiesEvent;
use OCP\App\IAppManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\INavigationManager;

/**
 * A capability catalog, not a dump of every PHP event or installed app. Applications appear
 * only when they have an implemented adapter and are enabled. Future integrations can register
 * bounded capabilities via Event.
 */
final class TriggerCatalog {
	public function __construct(
		private IAppManager $apps,
		private IEventDispatcher $dispatcher,
		private IAppConfig $config,
		private INavigationManager $navigation,
		private WebRouteCatalog $webRoutes,
		private NativeEventCatalog $nativeEvents,
		private GatewayTransport $gatewayTransport,
		private \OCP\IL10N $l10n,
	) {
	}

	public function intents(): array {
		return [
			['id' => 'app', 'label' => $this->l10n->t('In an app'), 'description' => $this->l10n->t('Action performed or visit to an application page'), 'icon' => 'application-outline'],
			['id' => 'schedule', 'label' => $this->l10n->t('On a date or during a period'), 'description' => $this->l10n->t('During a publication window'), 'icon' => 'calendar-clock-outline'],
			['id' => 'board', 'label' => $this->l10n->t('On publication activation'), 'description' => $this->l10n->t('Queue outbound channels on activation; banners and dialogs appear on the next eligible visit'), 'icon' => 'bulletin-board'],
			['id' => 'advanced', 'label' => $this->l10n->t('Advanced rule or integration'), 'description' => $this->l10n->t('Use Flow conditions or an external system'), 'icon' => 'source-branch'],
		];
	}

	public function sources(): array {
		return $this->enabledUniqueSources(array_merge(
			$this->builtInSources(),
			$this->nativeSources(),
			$this->registeredSources(),
		));
	}

	private function builtInSources(): array {
		$sources = [
			[
				'id' => 'files', 'appId' => 'files', 'intent' => 'action',
				'label' => $this->l10n->t('Files'), 'description' => $this->l10n->t('File creation, update or deletion in Files'),
				'keywords' => ['file','document','send','upload','modify','delete','folder'],
				'actions' => [
					['key' => 'nextcloud.files.created','label' => $this->l10n->t('File created'),'description' => $this->l10n->t('After someone uploads or creates a file')],
					['key' => 'nextcloud.files.updated','label' => $this->l10n->t('File updated'),'description' => $this->l10n->t('After an existing file is modified')],
					['key' => 'nextcloud.files.deleted','label' => $this->l10n->t('File deleted'),'description' => $this->l10n->t('After a file is deleted')],
				],
			],
			[
				'id' => 'sharing', 'appId' => 'files_sharing', 'intent' => 'action',
				'label' => $this->l10n->t('Shares'), 'description' => $this->l10n->t('Share creation or acceptance'),
				'keywords' => ['share','share','accept','recipient','permission'],
				'actions' => [
					['key' => 'nextcloud.share.created','label' => $this->l10n->t('Share created'),'description' => $this->l10n->t('When someone shares an item')],
					['key' => 'nextcloud.share.accepted','label' => $this->l10n->t('Share accepted'),'description' => $this->l10n->t('When someone accepts a shared item')],
				],
			],
			[
				'id' => 'users', 'appId' => 'core', 'intent' => 'action',
				'label' => $this->l10n->t('Accounts and access'), 'description' => $this->l10n->t('Authenticated user logins'),
				'keywords' => ['login','access','enter','account','welcome'],
				'actions' => [
					['key' => 'nextcloud.user.login','label' => $this->l10n->t('Interactive login'),'description' => $this->l10n->t('On each login through the login form')],
					['key' => 'nextcloud.user.first_login','label' => $this->l10n->t('First login'),'description' => $this->l10n->t('When an account logs in for the first time')],
				],
			],
			[
				'id' => 'comments', 'appId' => 'comments', 'intent' => 'action',
				'label' => $this->l10n->t('Comments'), 'description' => $this->l10n->t('Messages posted in Nextcloud comments'),
				'keywords' => ['comment','comment','message'],
				'actions' => [
					['key' => 'nextcloud.comment.created','label' => $this->l10n->t('Comment published'),'description' => $this->l10n->t('After someone posts a comment')],
				],
			],
			[
				'id' => 'navigation', 'appId' => 'core', 'intent' => 'visit',
				'label' => $this->l10n->t('Navigation'), 'description' => $this->l10n->t('Display on entry to a supported app'),
				'keywords' => ['open','enter','visit','files','dashboard','page','app'],
				'actions' => [
					['key' => 'nextcloud.page.opened','label' => $this->l10n->t('Application page opened'),
						'description' => $this->l10n->t('On entry to the area selected in “Where to display”')],
				],
			],
			[
				'id' => 'board', 'appId' => 'communications', 'intent' => 'board',
				'label' => $this->l10n->t('Immediate publication'), 'description' => $this->l10n->t('Deliver the message without waiting for a technical event'),
				'keywords' => ['board','announcement','notice','publish','news','history','immediate'],
				'actions' => [
					['key' => 'nextcloud.board.published','label' => $this->l10n->t('Immediate publication'),
						'description' => $this->l10n->t('Immediate queued delivery, banner or modal on next visit and optional Inbox')],
				],
			],
			[
				'id' => 'scheduler', 'appId' => 'core', 'intent' => 'schedule',
				'label' => $this->l10n->t('Scheduling'), 'description' => $this->l10n->t('Available during the publication period'),
				'keywords' => ['data','scheduled','period','hour','schedule'],
				'actions' => [
					['key' => 'nextcloud.time.window','label' => $this->l10n->t('Publication available during the period'),
						'description' => $this->l10n->t('On the next eligible visit within the configured dates')],
				],
			],
			[
				'id' => 'flow', 'appId' => 'workflowengine', 'intent' => 'advanced',
				'label' => 'Nextcloud Flow', 'description' => $this->l10n->t('Advanced conditions on files and actions'),
				'keywords' => ['rule','flow','mime','type','extension','folder','condition','automation'],
				'actionUrl' => '/index.php/settings/admin/workflow',
				'setup' => $this->l10n->t('Save the campaign, then create a conditional file rule in Flow. Choose the “Trigger contextual campaign” operation and select this campaign by name.'),
				'actions' => [
					['key' => 'nextcloud.flow.matched','label' => $this->l10n->t('A Flow rule matched'),
						'description' => $this->l10n->t('Flow checks the filters; this app only controls delivery')],
				],
			],
		];
		if (strlen($this->config->getValueString('communications', 'webhook_secret', '')) >= 32) {
			$sources[] = [
				'id' => 'webhook','appId' => 'communications','intent' => 'advanced',
				'label' => $this->l10n->t('External webhook'), 'description' => $this->l10n->t('An authorized external system reports an event for a specific user'),
				'keywords' => ['api','webhook','integration','external','mautic','system'],
				'setup' => $this->l10n->t('The integration sends an HMAC request containing campaignId (THIS campaign\'s ID), uid (ONE Nextcloud user) and eventId (unique event ID). Only this campaign and user receive the event, subject to audience and frequency rules. No broadcast to all users occurs. See docs/API.md.'),
				'actions' => [
					['key' => 'external.webhook','label' => $this->l10n->t('Campaign-and-user HMAC event'),
						'description' => $this->l10n->t('Record the event only for campaignId and uid specified in the webhook')],
				],
			];
		}
		return $sources;
	}

	private function nativeSources(): array {
		$sources = [];
		$nativeByApp = [];
		foreach ($this->nativeEvents->all() as $native) {
			$app = $native['appId'];
			if (!isset($nativeByApp[$app])) {
				$info = $app === 'core' ? null : $this->apps->getAppInfo($app);
				$appLabel = is_array($info) && is_string($info['name'] ?? null) ? $info['name'] : $app;
				$nativeByApp[$app] = [
					'id' => 'auto_' . $app, 'appId' => $app, 'intent' => 'action',
					'label' => $appLabel, 'description' => $this->l10n->t('Automatically discovered typed PHP events'),
					'keywords' => ['event','php','automatic','eventdispatcher'],
					'actions' => [],
				];
			}
			if (count($nativeByApp[$app]['actions']) >= 60) {
				continue;
			}
			$nativeByApp[$app]['actions'][] = [
				'key' => $native['key'], 'label' => $native['label'],
				'description' => $native['description'],
				'eventClass' => $native['class'], 'phase' => $native['phase'],
				'webhookCompatible' => $native['webhook'],
			];
		}
		foreach ($nativeByApp as $source) {
			if ($source['actions'] !== []) {
				$sources[] = $source;
			}
		}
		return $sources;
	}

	private function registeredSources(): array {
		$sources = [];
		$event = new RegisterTriggerCapabilitiesEvent();
		$this->dispatcher->dispatchTyped($event);
		foreach ($event->getSources() as $external) {
			if (!$this->validateExternal($external)) {
				continue;
			}
			$sources[] = $external;
		}
		return $sources;
	}

	private function enabledUniqueSources(array $sources): array {
		$known = [];
		$filtered = [];
		foreach ($sources as $source) {
			$app = $source['appId'];
			if ($app !== 'core' && !$this->apps->isEnabledForAnyone($app)) {
				continue;
			}
			// Avoid spoofing built-in keys and duplicates from different providers.
			$validActions = [];
			foreach ($source['actions'] as $action) {
				$key = $action['key'];
				if (isset($known[$key])) {
					continue;
				}
				$known[$key] = true;
				$validActions[] = $action;
			}
			if ($validActions === []) {
				continue;
			}
			$source['actions'] = $validActions;
			$filtered[] = $source;
		}
		return $filtered;
	}


	private function validateExternal(array $source): bool {
		foreach (['id','appId','intent','label','description','actions'] as $key) {
			if (!isset($source[$key])) {
				return false;
			}
		}
		if (!is_string($source['id']) || !preg_match('/^[a-z0-9_-]{2,40}$/', $source['id'])) {
			return false;
		}
		if (!is_string($source['appId']) || !preg_match('/^[a-z0-9_-]{2,40}$/', $source['appId'])) {
			return false;
		}
		if (!in_array($source['intent'], ['action','visit','schedule','advanced'], true)) {
			return false;
		}
		if (!is_string($source['label']) || mb_strlen($source['label']) > 80
			|| !is_string($source['description']) || mb_strlen($source['description']) > 240) {
			return false;
		}
		if (!is_array($source['actions']) || count($source['actions']) === 0 || count($source['actions']) > 30) {
			return false;
		}
		foreach ($source['actions'] as $action) {
			if (!is_array($action) || !isset($action['key'],$action['label'],$action['description'])
				|| !is_string($action['key']) || !preg_match('/^' . preg_quote($source['appId'], '/') . '\.[a-z0-9_.-]{1,40}$/', $action['key'])
				|| !is_string($action['label']) || mb_strlen($action['label']) > 80
				|| !is_string($action['description']) || mb_strlen($action['description']) > 240) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Installed and navigable applications only. Never suggest a server-only
	 * app as a landing page. The app ID is also used as the browser context.
	 */
	public function placements(): array {
		$options = [
			['value' => 'any','label' => $this->l10n->t('Any compatible page')],
			['value' => 'settings','label' => $this->l10n->t('Personal settings')],
			['value' => 'admin_settings','label' => $this->l10n->t('Administration settings')],
		];
		$seen = ['any' => true,'settings' => true,'admin_settings' => true];
		$navIcons = [];
		foreach ($this->navigation->getAll() as $item) {
			$href = $item['href'] ?? '';
			if (!is_string($href)) {
				continue;
			}
			if (!preg_match('~/(?:index\.php/)?apps/([a-z0-9_-]{2,32})(?:/|$)~', $href, $matches)) {
				continue;
			}
			$app = $matches[1];
			$navIcons[$app] = $item['icon'] ?? null;
			// Campaigns are not presented over their own administrative editor.
			if ($app === 'communications' || isset($seen[$app]) || !$this->apps->isEnabledForAnyone($app)) {
				continue;
			}
			if ($this->webRoutes->routesFor($app) === []) {
				continue;
			}
			$seen[$app] = true;
			$options[] = ['value' => $app,'label' => is_string($item['name'] ?? null) ? $item['name'] : $app,
				'icon' => $this->safeIcon($navIcons[$app] ?? $this->apps->getAppIcon($app))];
		}
		// Discover every enabled app declaring navigation in its installed info.xml,
		// even when an app's navigation entry is not loaded for this request.
		// Do not promote a server-only app to a UI merely because it is installed.
		foreach ($this->apps->getEnabledApps() as $app) {
			if ($app === 'communications' || isset($seen[$app]) || !$this->apps->isEnabledForAnyone($app)) {
				continue;
			}
			$info = $this->apps->getAppInfo($app);
			if (empty($info['navigations']['navigation']) || $this->webRoutes->routesFor($app) === []) {
				continue;
			}
			$seen[$app] = true;
			$options[] = [
				'value' => $app,
				'label' => is_string($info['name'] ?? null) ? $info['name'] : $app,
				'icon' => $this->safeIcon($this->apps->getAppIcon($app)),
			];
		}
		usort($options, static function (array $a, array $b): int {
			return ($a['value'] === 'any' ? -1 : ($b['value'] === 'any' ? 1 : 0))
				?: strcasecmp($a['label'], $b['label']);
		});
		return $options;
	}

	/**
	 * Configurable applications include event adapters and real navigable GUIs.
	 * An installed app without an event adapter can still offer page visits.
	 * Applications that only expose APIs are not page origins.
	 */
	private function safeIcon(?string $url): ?string {
		// Never expose a third-party tracking image from an untrusted navigation entry.
		return $url !== null && preg_match('~^/(?!/)[^\\\\?&#<>"\x00-\x1f]+\\.(?:svg|png)$~i', $url) ? $url : null;
	}

	public function applications(): array {
		$byApp = [];
		foreach ($this->sources() as $source) {
			if ($source['intent'] !== 'action') {
				continue;
			}
			$id = $source['appId'];
			if (!isset($byApp[$id])) {
				$byApp[$id] = [
					'id' => $id, 'label' => $source['label'],
					'icon' => $this->safeIcon($this->apps->getAppIcon($id)),
					'description' => $source['description'],
					'keywords' => $source['keywords'] ?? [],
					'canEvents' => true, 'canPages' => false,
					'actions' => $source['actions'],
				];
			} else {
				$byApp[$id]['canEvents'] = true;
				$byApp[$id]['actions'] = array_merge($byApp[$id]['actions'], $source['actions']);
			}
		}
		foreach ($this->placements() as $placement) {
			$id = $placement['value'];
			if (in_array($id, ['any','settings','admin_settings'], true)) {
				continue;
			}
			if (isset($byApp[$id])) {
				$byApp[$id]['canPages'] = true;
				$byApp[$id]['icon'] = $placement['icon'] ?? $byApp[$id]['icon'];
			} else {
				$byApp[$id] = [
					'id' => $id, 'label' => $placement['label'],
					'icon' => $placement['icon'] ?? null,
					'description' => $this->l10n->t('Pages reachable through Nextcloud navigation'),
					'keywords' => ['page','route','access','open'],
					'canEvents' => false, 'canPages' => true, 'actions' => [],
				];
			}
		}
		$apps = array_values($byApp);
		usort($apps, static fn (array $a, array $b):int => strcasecmp($a['label'], $b['label']));
		return $apps;
	}

	/**
	 * Real adapter readiness versus app installation. Returning a capability
	 * never enables an unimplemented external delivery adapter.
	 */
	public function deliveryChannels(): array {
		$notifications = $this->apps->isEnabledForAnyone('notifications');
		$activity = $this->apps->isEnabledForAnyone('activity');
		$gateway = $this->gatewayTransport->installed();
		$sources = [
			[
				'id' => 'notifications', 'label' => $this->l10n->t('Nextcloud Notifications'),
				'installed' => $notifications, 'ready' => $notifications,
				'availability' => $notifications ? 'ready' : 'missing_app',
				'status' => $notifications ? $this->l10n->t('Available') : $this->l10n->t('Not installed'),
				'help' => $notifications
					? $this->l10n->t('Sends a bell notification to each authorized recipient with a restricted link, without requiring an Inbox copy.')
					: $this->l10n->t('Notifications is not enabled in this Nextcloud. An administrator must install and enable the Notifications app before using bell notifications. The notify_push service does not replace Notifications.'),
			],
			[
				'id' => 'activity', 'label' => 'Activity do Nextcloud',
				'installed' => $activity, 'ready' => $activity,
				'availability' => $activity ? 'ready' : 'missing_app',
				'status' => $activity ? $this->l10n->t('Available') : $this->l10n->t('Not installed'),
				'help' => $activity
					? $this->l10n->t('Records an Activity entry for the authorized recipient with a restricted details link. No Inbox copy required.')
					: $this->l10n->t('Activity is not enabled in this Nextcloud. Install and enable Activity before recording notifications through that channel.'),
			],
			[
				'id' => 'email', 'label' => 'E-mail',
				'installed' => true, 'ready' => true, 'availability' => 'requires_configuration',
				'status' => $this->l10n->t('Check SMTP'),
				'help' => $this->l10n->t('The email adapter is available. Real delivery requires a mail server configured by the Nextcloud administrator and valid recipient addresses. This catalog does not verify SMTP availability.'),
			],
			[
				'id' => 'twofactor_gateway','label' => 'Two-Factor Gateway',
				'installed' => $gateway,'ready' => false,
				'availability' => $gateway ? 'configured_per_provider' : 'missing_app',
				'status' => $gateway ? $this->l10n->t('View channels') : $this->l10n->t('Not installed'),
				'help' => $gateway
					? $this->l10n->t('Uses the native direct-provider service of Two-Factor Gateway. Configure providers and require explicit recipient consent in notification preferences.')
					: $this->l10n->t('Install and configure Two-Factor Gateway for SMS, Signal, Telegram, WhatsApp or XMPP. Delivery requires a provider and recipient consent.'),
			],
		];
		foreach (GatewayTransport::LABELS as $id => $label) {
			$ready = $this->gatewayTransport->configured($id);
			$sources[] = [
				'id' => 'gateway:' . $id,'label' => $label,
				'installed' => $gateway,'ready' => $ready,
				'availability' => !$gateway ? 'missing_app' : ($ready ? 'ready' : 'missing_configuration'),
				'status' => !$gateway ? $this->l10n->t('Not installed') : ($ready ? $this->l10n->t('Available') : $this->l10n->t('Not configured')),
				'help' => !$gateway
					? $this->l10n->t('Two-Factor Gateway must be installed. This channel remains inactive and sends no messages.')
					: ($ready
					? $this->l10n->t('Direct gateway configured. Each recipient must supply an identifier and authorize this channel in Inbox → Notification preferences; sponsored content requires additional consent.')
					: $this->l10n->t('This provider has no fully configured direct gateway in Two-Factor Gateway. Configure it under Administration → Security before selecting it.')),
			];
		}
		return $sources;
	}

	public function supportsPlacement(string $placement): bool {
		foreach ($this->placements() as $page) {
			if ($page['value'] === $placement) {
				return true;
			}
		}
		return false;
	}

	public function supports(string $key): bool {
		foreach ($this->sources() as $source) {
			foreach ($source['actions'] as $action) {
				if ($action['key'] === $key) {
					return true;
				}
			}
		}
		return false;
	}

	public function legacyTriggers(): array {
		$out = [];
		foreach ($this->sources() as $source) {
			foreach ($source['actions'] as $action) {
				$out[] = ['key' => $action['key'], 'label' => $action['label']];
			}
		}
		return $out;
	}
}
