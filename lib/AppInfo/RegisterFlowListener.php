<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\AppInfo;

use OCA\Communications\Service\FlowCampaignOperation;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Util;
use OCP\WorkflowEngine\Events\LoadSettingsScriptsEvent;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

class RegisterFlowListener implements IEventListener {
	public function __construct(
		private FlowCampaignOperation $operation,
	) {
	}
	public function handle(Event $event): void {
		if ($event instanceof RegisterOperationsEvent) {
			$event->registerOperation($this->operation);
		} elseif ($event instanceof LoadSettingsScriptsEvent) {
			Util::addScript('communications', 'flow', 'workflowengine');
			Util::addStyle('communications', 'flow');
		}
	}
}
