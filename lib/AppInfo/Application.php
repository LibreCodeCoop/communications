<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\NativeEventSubscriptions;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Comments\Events\CommentAddedEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Events\ShareAcceptedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\User\Events\UserLoggedInEvent;
use OCP\Util;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

class Application extends App implements IBootstrap {
	public function __construct() {
		parent::__construct('communications');
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(\OCA\Communications\Dashboard\BoardWidget::class);
		$context->registerNotifierService(\OCA\Communications\Notification\BoardNotifier::class);
		$context->registerEventListener(UserLoggedInEvent::class, LoginListener::class);
		$context->registerEventListener(NodeCreatedEvent::class, FileCreatedListener::class);
		$this->registerNativeListeners($context);
		$context->registerEventListener(RegisterOperationsEvent::class, RegisterFlowListener::class);
		$context->registerEventListener(LoadSettingsScriptsEvent::class, RegisterFlowListener::class);
		foreach ([
			UserFirstTimeLoggedInEvent::class,
			NodeWrittenEvent::class,
			NodeDeletedEvent::class,
			ShareCreatedEvent::class,
			ShareAcceptedEvent::class,
			CommentAddedEvent::class,
		] as $eventClass) {
			$context->registerEventListener($eventClass, CoreEventsListener::class);
		}
	}

	private function registerNativeListeners(IRegistrationContext $context): void {
		// Bootstrap listeners also receive DAV and non-UI events.
		$compiled = $this->getContainer()->get(\OCP\IAppConfig::class)
			->getValueString('communications', 'native_active_event_subscriptions_v1', '[]');
		foreach (NativeEventSubscriptions::bootstrapClasses($compiled, (string)($_SERVER['REQUEST_METHOD'] ?? '')) as $class) {
			$context->registerEventListener($class, NativeEventListener::class, -100);
		}
	}

	public function boot(IBootContext $context): void {
		if (PHP_SAPI === 'cli') {
			return;
		}
		$server = $context->getServerContainer();
		$user = $server->get(IUserSession::class)->getUser();
		$path = $server->get(IRequest::class)->getPathInfo();
		if ($user === null && (preg_match('~^/(?:index\.php/)?login/?$~', $path)
			|| preg_match('~^/(?:index\.php/)?(?:s|f)/[A-Za-z0-9_-]{8,120}/?$~', $path))) {
			Util::addTranslations('communications');
			Util::addScript('communications', 'guest');
			Util::addStyle('communications', 'guest');
		}
		if ($user !== null && !str_contains($path, '/apps/communications')) {
			Util::addTranslations('communications');
			Util::addScript('communications', 'loader');
		}
		if ($user !== null) {
			$server->get(INavigationManager::class)->add(function () use ($server): array {
				$urls = $server->get(IURLGenerator::class);
				return [
					'id' => 'communications',
					'app' => 'communications',
					'type' => 'link',
					'order' => 97,
					'name' => $server->get(\OCP\IL10N::class)->t('Communications'),
					'href' => $urls->linkToRoute('communications.page.index'),
					'icon' => $urls->imagePath('communications', 'app.svg'),
				];
			});
		}
	}
}
