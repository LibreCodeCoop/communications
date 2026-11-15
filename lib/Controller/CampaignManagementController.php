<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Controller;

use InvalidArgumentException;
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EditorAccess;
use OCA\Communications\Service\TriggerCatalog;
use OCA\Communications\Service\WebRouteCatalog;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class CampaignManagementController extends Controller {
	public function __construct(
		IRequest $request,
		private CampaignRepository $campaigns,
		private DeliveryStore $deliveries,
		private TriggerCatalog $triggerCatalog,
		private WebRouteCatalog $webRoutes,
		private IUserSession $userSession,
		private EditorAccess $editorAccess,
		private \OCP\IL10N $l10n,
	) {
		parent::__construct('communications', $request);
	}
	private function uid():?string {
		return $this->userSession->getUser()?->getUID();
	}
	private function denied():?JSONResponse {
		return $this->editorAccess->canEdit($this->uid()) ? null
			: new JSONResponse(['error' => $this->l10n->t('Editorial permission required.')], 403);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/campaigns')]
	public function index(): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		return new JSONResponse(['campaigns' => $this->campaigns->all()]);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/campaigns/{id}')]
	public function show(int $id): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		$item = $this->campaigns->find($id);
		return $item !== null ? new JSONResponse(['campaign' => $item]) : new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/catalog')]
	public function catalog(): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		return new JSONResponse([
			'triggers' => $this->triggerCatalog->legacyTriggers(),
			'automation' => [
				'intents' => $this->triggerCatalog->intents(),
				'sources' => $this->triggerCatalog->sources(),
				'placements' => $this->triggerCatalog->placements(),
				'applications' => $this->triggerCatalog->applications(),
			],
			'deliveryChannels' => $this->triggerCatalog->deliveryChannels(),
			'groups' => $this->campaigns->groupOptions(),
			'statuses' => CampaignRepository::STATUSES,
			'presentations' => CampaignRepository::PRESENTATIONS,
			'canConfigureEditors' => $this->editorAccess->isAdmin($this->uid()),
			'editorGroups' => $this->editorAccess->isAdmin($this->uid()) ? $this->editorAccess->groups() : [],
		]);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/routes')]
	public function routes(string $app = ''): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app) || !$this->triggerCatalog->supportsPlacement($app)
			|| $app === 'any' || $app === 'settings') {
			return new JSONResponse(['error' => $this->l10n->t('App has no available web pages.')], 422);
		}
		return new JSONResponse(['app' => $app, 'routes' => $this->webRoutes->routesFor($app)]);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/campaigns/{id}/metrics')]
	public function metrics(int $id): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		if ($this->campaigns->find($id) === null) {
			return new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
		}
		return new JSONResponse(['campaignId' => $id, 'metrics' => $this->deliveries->metrics($id), 'audited' => false]);
	}

	#[NoCSRFRequired]
	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/api/users')]
	public function users(string $search = ''): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		return new JSONResponse(['users' => $this->campaigns->userOptions($search)]);
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/campaigns')]
	public function create(): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		try {
			$uid = $this->userSession->getUser()?->getUID();
			if ($uid === null) {
				return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
			}
			$campaign = $this->campaigns->create($this->request->getParams(), $uid);
			return new JSONResponse(['campaign' => $campaign], 201);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'PUT', url: '/api/campaigns/{id}')]
	public function update(int $id): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		try {
			$campaign = $this->campaigns->update($id, $this->request->getParams());
			return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/campaigns/{id}/status')]
	public function status(int $id, string $status = ''): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		try {
			$campaign = $this->campaigns->setStatus($id, $status);
			return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/campaigns/{id}/duplicate')]
	public function duplicate(int $id): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		$original = $this->campaigns->find($id);
		if ($original === null) {
			return new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
		}
		$original['name'] = mb_substr($original['name'], 0, 145) . $this->l10n->t(' (copy)');
		$original['status'] = 'draft';
		// A scheduled campaign requires its start date even in draft mode.
		// Preserve the original window on duplication so the copied campaign
		// passes validation and the editor can present meaningful dates.
		if ($original['trigger_key'] !== 'nextcloud.time.window') {
			$original['starts_at'] = null;
			$original['ends_at'] = null;
		}
		try {
			$uid = $this->userSession->getUser()?->getUID();
			if ($uid === null) {
				return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
			}
			return new JSONResponse(['campaign' => $this->campaigns->create($original, $uid)], 201);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/campaigns/{id}/move')]
	public function move(int $id, string $direction = '', ?int $target_id = null):JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		try {
			$items = $this->campaigns->move($id, $direction, $target_id);
			if ($items === null) {
				return new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
			}
			return new JSONResponse([
				'order' => array_map(static fn (array $row): int => (int)$row['id'], $items),
			]);
		} catch (InvalidArgumentException $e) {
			return new JSONResponse(['error' => $e->getMessage()], 422);
		}
	}

	#[UseSession]
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'DELETE', url: '/api/campaigns/{id}')]
	public function archive(int $id): JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		$campaign = $this->campaigns->setStatus($id, 'archived');
		return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => $this->l10n->t('Campaign not found.')], 404);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/campaigns/{id}/purge')]
	public function purge(int $id, string $confirmName = ''):JSONResponse {
		if (($deny = $this->denied()) !== null) {
			return $deny;
		}
		try {
			if (!$this->campaigns->purgeArchived($id, $confirmName)) {
				return new JSONResponse(['error' => $this->l10n->t('Publication not found.')], 404);
			}
			return new JSONResponse(['deleted' => true, 'id' => $id]);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/editor-groups')]
	public function editorGroups(): JSONResponse {
		if (!$this->editorAccess->isAdmin($this->uid())) {
			return new JSONResponse(['error' => $this->l10n->t('Only administrators can view delegation settings.')], 403);
		}
		return new JSONResponse(['groups' => $this->editorAccess->groups()]);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/editor-groups')]
	public function setEditorGroups(): JSONResponse {
		if (!$this->editorAccess->isAdmin($this->uid())) {
			return new JSONResponse(['error' => $this->l10n->t('Only administrators can delegate editing.')], 403);
		}
		$groups = $this->request->getParam('groups', []);
		if (!is_array($groups)) {
			return new JSONResponse(['error' => $this->l10n->t('Invalid group list.')], 422);
		}
		try {
			return new JSONResponse(['groups' => $this->editorAccess->configure($this->uid(), $groups)]);
		} catch (InvalidArgumentException $error) {
			return new JSONResponse(['error' => $error->getMessage()], 422);
		}
	}
}
