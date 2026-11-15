<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Controller;

use JsonException;
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\EventStore;
use OCA\Communications\Service\WebhookSignatureVerifier;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * Optional HMAC-signed inbound integration for business events.
 */
class WebhookController extends Controller {
	public function __construct(
		IRequest $request,
		private IAppConfig $config,
		private IUserManager $users,
		private EventStore $events,
		private CampaignRepository $campaigns,
		private LoggerInterface $logger,
		private WebhookSignatureVerifier $signatureVerifier,
		private IL10N $l10n,
	) {
		parent::__construct('communications', $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[FrontpageRoute(verb: 'POST', url: '/api/hooks/event')]
	public function ingest(): JSONResponse {
		$secret = $this->config->getValueString('communications', 'webhook_secret', '');
		if (strlen($secret) < 32) {
			// TRANSLATORS Short label for an option or field in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Webhook not configured.')], 503);
		}
		$raw = file_get_contents('php://input');
		if (!is_string($raw) || strlen($raw) > 8192) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid content.')], 400);
		}
		$timestamp = $this->request->getHeader('X-CC-Timestamp');
		$provided = $this->request->getHeader('X-CC-Signature');
		if (!$this->signatureVerifier->valid($secret, $raw, $timestamp, $provided, time())) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid signature.')], 401);
		}
		try {
			$payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid JSON.')], 422);
		}
		if (!is_array($payload)) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid request body.')], 422);
		}
		$uid = $payload['uid'] ?? null;
		$externalEventId = $payload['eventId'] ?? null;
		$campaignId = $payload['campaignId'] ?? null;
		if (!is_string($uid) || $uid === '' || mb_strlen($uid) > 64 || $this->users->get($uid) === null
			|| !is_string($externalEventId) || $externalEventId === '' || strlen($externalEventId) > 128) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid recipient or event.')], 422);
		}
		if (filter_var($campaignId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
			// TRANSLATORS Descriptive message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Provide the campaignId associated with this webhook.')], 422);
		}
		$campaignId = (int)$campaignId;
		$campaign = $this->campaigns->find($campaignId);
		if ($campaign === null || $campaign['trigger_key'] !== 'external.webhook'
			|| $campaign['status'] !== 'active') {
			// TRANSLATORS Descriptive message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Campaign does not exist, is inactive, or is not configured for webhooks.')], 422);
		}
		try {
			$event = $this->events->record($uid, 'external.webhook', 'campaign_' . $campaignId,
				'webhook:' . $campaignId . ':' . $externalEventId);
			return new JSONResponse(['accepted' => true, 'eventId' => $event['event_uuid']], 202);
		} catch (\InvalidArgumentException) {
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Invalid recipient or event.')], 422);
		} catch (\OCP\DB\Exception|\RuntimeException $error) {
			$this->logger->error('Webhook event storage failed', ['exceptionClass' => $error::class]);
			// TRANSLATORS Validation or failure message shown in the WebhookController API response.
			return new JSONResponse(['error' => $this->l10n->t('Could not record event.')], 500);
		}
	}
}
