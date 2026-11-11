<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Service;

use OCA\WorkflowEngine\Entity\File as FileEntity;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\File;
use OCP\IURLGenerator;
use OCP\WorkflowEngine\IManager;
use OCP\WorkflowEngine\IRuleMatcher;
use OCP\WorkflowEngine\ISpecificOperation;
use Psr\Log\LoggerInterface;

/**
 * Optional Nextcloud Flow operation. Each matching rule contains a single
 * campaign ID, and events are scoped to that ID. No arbitrary user data is
 * passed to external services.
 */
class FlowCampaignOperation implements ISpecificOperation {
 public function __construct(
  private CampaignRepository $campaigns,
  private EventStore $events,
  private IURLGenerator $urls,
  private LoggerInterface $logger,
 ) {}

 public function getEntityId(): string { return FileEntity::class; }
 public function getDisplayName(): string { return 'Disparar campanha contextual'; }
 public function getDescription(): string { return 'Quando a regra for atendida, apresentar uma campanha escolhida pelo nome.'; }
 public function getIcon(): string { return $this->urls->imagePath('communications', 'app.svg'); }
 public function isAvailableForScope(int $scope): bool { return $scope === IManager::SCOPE_ADMIN; }
 public function getTriggerHint(): string { return 'Um evento de arquivo cumpre uma regra de campanha'; }

 public function validateOperation(string $name, array $checks, string $operation): void {
  if (!ctype_digit($operation) || (int)$operation <= 0) {
   throw new \UnexpectedValueException('Informe o ID numérico da campanha.');
  }
  $campaign = $this->campaigns->find((int)$operation);
  if ($campaign === null || $campaign['trigger_key'] !== 'nextcloud.flow.matched') {
   throw new \UnexpectedValueException('A campanha deve existir e usar o gatilho Nextcloud Flow.');
  }
 }

 public function onEvent(string $eventName, Event $event, IRuleMatcher $matcher): void {
  // The Flow engine's file entity dispatches legacy GenericEvent objects,
  // not modern NodeCreatedEvent/NodeWrittenEvent. Use the event contract the
  // registered entity actually provides, after Flow has evaluated its checks.
  if (!$event instanceof GenericEvent || !in_array($eventName, ['\\OCP\\Files::postCreate', '\\OCP\\Files::postWrite'], true)) { return; }
  $node = $event->getSubject();
  if (!$node instanceof File) { return; }
  $owner = $node->getOwner();
  if ($owner === null || !str_starts_with($node->getPath(), '/' . $owner->getUID() . '/files/')) {
   return;
  }
  try {
   foreach ($matcher->getFlows(false) as $rule) {
    $id = (int)($rule['operation'] ?? 0);
    $campaign = $this->campaigns->find($id);
    if ($campaign === null || $campaign['trigger_key'] !== 'nextcloud.flow.matched') { continue; }
    $this->events->record($owner->getUID(), 'nextcloud.flow.matched', 'campaign_' . $id);
   }
  } catch (\Throwable $e) {
   $this->logger->warning('Flow campaign rule failed without stopping Files', ['exception'=>$e]);
  }
 }
}
