<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Optional HMAC-signed inbound integration for business events.
 */
namespace OCA\Communications\Controller;

use JsonException;
use OCA\Communications\Service\EventStore;
use OCA\Communications\Service\CampaignRepository;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class WebhookController extends Controller {
 public function __construct(
  IRequest $request,
  private IAppConfig $config,
  private IUserManager $users,
  private EventStore $events,
  private CampaignRepository $campaigns,
  private LoggerInterface $logger,
 ) { parent::__construct('communications', $request); }

 #[PublicPage]
 #[NoCSRFRequired]
 public function ingest(): JSONResponse {
  $secret = $this->config->getValueString('communications', 'webhook_secret', '');
  if (strlen($secret) < 32) {
   return new JSONResponse(['error'=>'Webhook não configurado.'], 503);
  }
  $raw = file_get_contents('php://input');
  if (!is_string($raw) || strlen($raw) > 8192) {
   return new JSONResponse(['error'=>'Conteúdo inválido.'], 400);
  }
  $timestamp = $this->request->getHeader('X-CC-Timestamp');
  $provided = $this->request->getHeader('X-CC-Signature');
  if (!ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 300
      || !preg_match('/^sha256=[a-f0-9]{64}$/', $provided)) {
   return new JSONResponse(['error'=>'Assinatura inválida.'], 401);
  }
  $expected = 'sha256=' . hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
  if (!hash_equals($expected, $provided)) {
   return new JSONResponse(['error'=>'Assinatura inválida.'], 401);
  }
  try {
   $payload = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
  } catch (JsonException $e) {
   return new JSONResponse(['error'=>'JSON inválido.'], 422);
  }
  if (!is_array($payload)) { return new JSONResponse(['error'=>'Corpo inválido.'], 422); }
  $uid = $payload['uid'] ?? null;
  $externalEventId = $payload['eventId'] ?? null;
  $campaignId = $payload['campaignId'] ?? null;
  if (!is_string($uid) || $uid === '' || mb_strlen($uid) > 64 || $this->users->get($uid) === null
      || !is_string($externalEventId) || $externalEventId === '' || strlen($externalEventId) > 128) {
   return new JSONResponse(['error'=>'Destinatário ou evento inválido.'], 422);
  }
  if (filter_var($campaignId, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) === false) {
   return new JSONResponse(['error'=>'Informe campaignId da campanha associada ao webhook.'],422);
  }
  $campaignId = (int)$campaignId;
  $campaign = $this->campaigns->find($campaignId);
  if ($campaign === null || $campaign['trigger_key'] !== 'external.webhook'
      || $campaign['status'] !== 'active') {
   return new JSONResponse(['error'=>'Campanha inexistente, inativa ou não configurada para webhooks.'],422);
  }
  try {
   // One event belongs to exactly one campaign and one UID. Never broadcast
   // one external event to unrelated campaigns that listen to the same trigger.
   $event = $this->events->record($uid, 'external.webhook', 'campaign_' . $campaignId,
    'webhook:' . $campaignId . ':' . $externalEventId);
   return new JSONResponse(['accepted'=>true, 'eventId'=>$event['event_uuid']], 202);
  } catch (\Throwable $e) {
   $this->logger->error('Contextual campaigns incoming webhook failure', ['exception'=>$e]);
   return new JSONResponse(['error'=>'Não foi possível registrar o evento.'], 500);
  }
 }
}
