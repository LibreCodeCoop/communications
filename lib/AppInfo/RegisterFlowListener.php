<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\FlowCampaignOperation;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\Util;

class RegisterFlowListener implements IEventListener {
 public function __construct(private FlowCampaignOperation $operation) {}
 public function handle(Event $event): void {
  if ($event instanceof RegisterOperationsEvent) {
   $event->registerOperation($this->operation);
  } elseif ($event instanceof LoadSettingsScriptsEvent) {
   Util::addScript('communications','flow','workflowengine');
   Util::addStyle('communications','flow');
  }
 }
}
