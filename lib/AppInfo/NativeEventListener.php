<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\EventStore;
use OCA\Communications\Service\NativeEventSubscriptions;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * One listener for any configured typed event.
 * Only the authenticated principal can be targeted: never infer recipient,
 * owner, signer or collaborator from arbitrary event objects.
 *
 * @template-implements IEventListener<Event>
 */
final class NativeEventListener implements IEventListener {
 public function __construct(
  private NativeEventSubscriptions $subscriptions,
  private EventStore $events,
  private IUserSession $session,
  private LoggerInterface $logger,
 ) {}

 public function handle(Event $event): void {
  $meta = $this->subscriptions->forClass($event::class);
  if ($meta === null) { return; }
  $user = $this->session->getUser();
  if ($user === null || !$user->isEnabled()) { return; }
  try {
   // Do not call event getters; they may expose documents, tokens,
   // signatures or owner accounts and can perform arbitrary work.
   $this->events->record($user->getUID(), $meta['key'], $meta['appId']);
  } catch (\Throwable $error) {
   // Campaign failures must not break the host application's operation.
   $this->logger->warning('Contextual campaigns could not capture a typed event', [
    'exceptionClass' => get_class($error),
   ]);
  }
 }
}
