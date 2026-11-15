<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Controller;

use OCA\Communications\Service\DeliveryStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;

class CampaignController extends Controller {
	public function __construct(
		IRequest $request,
		private IUserSession $userSession,
		private IConfig $config,
		private DeliveryStore $deliveries,
		private IL10N $l10n,
	) {
		parent::__construct('communications', $request);
	}

	private function uid(): ?string {
		return $this->userSession->getUser()?->getUID();
	}

	/**
	 * Diagnostic backwards-compatible list. No tab lease is taken here; production
	 * frontend uses POST /api/claim to ensure only one tab presents each delivery.
	 */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/pending')]
	public function pending(string $page = 'any', string $path = ''): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			return new JSONResponse(['campaigns' => []], 401);
		}
		$this->deliveries->prepare($uid, $page, false, $path);
		return new JSONResponse(['campaigns' => $this->deliveries->pending($uid, $page, 30, $path)]);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/claim')]
	public function claim(string $page = 'any', bool $newVisit = false, string $path = ''): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$item = $this->deliveries->claim($uid, $page, $newVisit, $path);
		return new JSONResponse(['campaign' => $item]);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/claim-batch')]
	public function claimBatch(string $page = 'any', bool $newVisit = false, string $path = '', int $limit = 5):JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return new JSONResponse($this->deliveries->claimBatch($uid, $page, $newVisit, $path, max(1, min($limit, 5))));
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/impression')]
	public function impression(int $deliveryId = 0, string $claimToken = ''): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return $this->deliveries->transition($uid, $deliveryId, $claimToken, 'impression')
			? new JSONResponse(['recorded' => true])
			// TRANSLATORS Descriptive message shown in the CampaignController API response.
			: new JSONResponse(['error' => $this->l10n->t('Delivery not found or reservation expired.')], 409);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/ack')]
	public function acknowledge(
		int $deliveryId = 0,
		string $claimToken = '',
		int $campaignId = 0,
		string $eventId = '',
	): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$ok = $deliveryId > 0 && $claimToken !== ''
			? $this->deliveries->transition($uid, $deliveryId, $claimToken, 'dismiss')
			: ($campaignId > 0 && $eventId !== '' && $this->deliveries->legacyAcknowledge($uid, $campaignId, $eventId));
		// TRANSLATORS Validation or failure message shown in the CampaignController API response.
		return $ok ? new JSONResponse(['acknowledged' => true]) : new JSONResponse(['error' => $this->l10n->t('Invalid delivery.')], 409);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/click')]
	public function click(int $deliveryId = 0, string $claimToken = ''): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return $this->deliveries->transition($uid, $deliveryId, $claimToken, 'click')
			? new JSONResponse(['recorded' => true])
			// TRANSLATORS Validation or failure message shown in the CampaignController API response.
			: new JSONResponse(['error' => $this->l10n->t('Invalid delivery.')], 409);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'GET', url: '/api/preferences')]
	public function preferences(): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		return new JSONResponse([
			'sponsoredOptout' => $this->config->getUserValue($uid, 'communications', 'sponsored_optout', '0') === '1',
		]);
	}

	#[NoAdminRequired]
	#[UseSession]
	#[FrontpageRoute(verb: 'POST', url: '/api/preferences')]
	public function setPreferences(bool $sponsoredOptout = false): JSONResponse {
		$uid = $this->uid();
		if ($uid === null) {
			// TRANSLATORS Short label for an option or field in the CampaignController API response.
			return new JSONResponse(['error' => $this->l10n->t('Authentication required.')], 401);
		}
		$this->config->setUserValue($uid, 'communications', 'sponsored_optout', $sponsoredOptout ? '1':'0');
		return new JSONResponse(['sponsoredOptout' => $sponsoredOptout]);
	}
}
