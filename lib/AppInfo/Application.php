<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\AppInfo;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\User\Events\UserLoggedInEvent;
use OCP\User\Events\UserFirstTimeLoggedInEvent;
use OCP\Files\Events\Node\NodeCreatedEvent;
use OCP\Files\Events\Node\NodeWrittenEvent;
use OCP\Files\Events\Node\NodeDeletedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\ShareAcceptedEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\Comments\Events\CommentAddedEvent;
use OCP\IGroupManager;
use OCP\IUserSession;
use OCP\IRequest;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\Util;
use OCA\Communications\Service\NativeEventSubscriptions;
use OCA\Communications\Service\EditorAccess;

class Application extends App implements IBootstrap {
 public function __construct() { parent::__construct('communications'); }

 public function register(IRegistrationContext $context): void {
  // Native Dashboard and Notifications integration. Providers are lazy and
  // validate group eligibility whenever they expose an announcement.
  $context->registerDashboardWidget(\OCA\Communications\Dashboard\BoardWidget::class);
  $context->registerNotifierService(\OCA\Communications\Notification\BoardNotifier::class);
  $context->registerEventListener(UserLoggedInEvent::class, LoginListener::class);
  $context->registerEventListener(NodeCreatedEvent::class, FileCreatedListener::class);
  // Nextcloud registers bootstrap listeners for ALL requests, including DAV,
  // before an individual app may be booted. Dynamic listeners belong HERE,
  // not in boot(), or non-UI events can be missed.
  $compiled = $this->getContainer()->get(\OCP\IAppConfig::class)
   ->getValueString('communications', 'native_active_event_subscriptions_v1', '[]');
  // Mutating events do not subscribe during safe HTTP GET/HEAD/OPTIONS.
  // This preserves fast page views even when a File-created campaign is active.
  foreach (NativeEventSubscriptions::bootstrapClasses($compiled, (string)($_SERVER['REQUEST_METHOD'] ?? '')) as $class) {
   $context->registerEventListener($class, NativeEventListener::class, -100);
  }
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

 public function boot(IBootContext $context): void {
  // CLI jobs have no browser path or navigation context.
  if (PHP_SAPI === 'cli') { return; }
  $server = $context->getServerContainer();
  $user = $server->get(IUserSession::class)->getUser();
  // Guest banners use a deliberately small and public-only script, not the
  // authenticated campaign engine. Only enable on browser-visible public pages.
  $path = $server->get(IRequest::class)->getPathInfo();
  if ($user===null && (preg_match('~^/(?:index\.php/)?login/?$~',$path)
      || preg_match('~^/(?:index\.php/)?(?:s|f)/[A-Za-z0-9_-]{8,120}/?$~',$path))) {
   Util::addScript('communications','guest');
   Util::addStyle('communications','guest');
  }
  if ($user !== null && !str_contains($path, '/apps/communications')) {
   // Most pages have no eligible campaign: load the small preflight script
   // and lazily inject the Vue renderer only after an actual claim.
   Util::addScript('communications', 'loader');
  }
  if ($user !== null) {
   $server->get(INavigationManager::class)->add(function() use ($server): array {
    $urls = $server->get(IURLGenerator::class);
    return [
     'id' => 'communications',
     'app' => 'communications',
     'type' => 'link',
     'order' => 97,
     'name' => 'Comunicações',
     'href' => $urls->linkToRoute('communications.page.index'),
     'icon' => $urls->imagePath('communications', 'app.svg'),
    ];
   });
  }
 }
}
