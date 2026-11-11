<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Controller;

use InvalidArgumentException;
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EditorAccess;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCA\Communications\Service\TriggerCatalog;
use OCA\Communications\Service\WebRouteCatalog;
use OCP\AppFramework\Controller;
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
 ) {
  parent::__construct('communications', $request);
 }
 private function uid():?string { return $this->userSession->getUser()?->getUID(); }
 private function denied():?JSONResponse {
  return $this->editorAccess->canEdit($this->uid()) ? null
   : new JSONResponse(['error'=>'Permissão editorial necessária.'],403);
 }

 #[NoCSRFRequired]
 #[UseSession]
 #[NoAdminRequired]
 public function index(): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  return new JSONResponse(['campaigns' => $this->campaigns->all()]);
 }

 #[NoCSRFRequired]
 #[UseSession]
 #[NoAdminRequired]
 public function show(int $id): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  $item = $this->campaigns->find($id);
  return $item !== null ? new JSONResponse(['campaign' => $item]) : new JSONResponse(['error' => 'Campanha não encontrada.'], 404);
 }

 #[NoCSRFRequired]
 #[UseSession]
 #[NoAdminRequired]
 public function catalog(): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
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
 public function routes(string $app = ''): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app) || !$this->triggerCatalog->supportsPlacement($app)
      || $app === 'any' || $app === 'settings') {
   return new JSONResponse(['error'=>'Aplicativo sem páginas web disponíveis.'], 422);
  }
  return new JSONResponse(['app'=>$app, 'routes'=>$this->webRoutes->routesFor($app)]);
 }

 #[NoCSRFRequired]
 #[UseSession]
 #[NoAdminRequired]
 public function metrics(int $id): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  if ($this->campaigns->find($id) === null) {
   return new JSONResponse(['error'=>'Campanha não encontrada.'], 404);
  }
  return new JSONResponse(['campaignId'=>$id, 'metrics'=>$this->deliveries->metrics($id), 'audited'=>false]);
 }

 #[NoCSRFRequired]
 #[UseSession]
 #[NoAdminRequired]
 public function users(string $search = ''): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  return new JSONResponse(['users' => $this->campaigns->userOptions($search)]);
 }

 #[UseSession]
 #[NoAdminRequired]
 public function create(): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  try {
   $uid = $this->userSession->getUser()?->getUID();
   if ($uid === null) { return new JSONResponse(['error' => 'Sessão necessária.'], 401); }
   $campaign = $this->campaigns->create($this->request->getParams(), $uid);
   return new JSONResponse(['campaign' => $campaign], 201);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error' => $error->getMessage()], 422);
  }
 }

 #[UseSession]
 #[NoAdminRequired]
 public function update(int $id): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  try {
   $campaign = $this->campaigns->update($id, $this->request->getParams());
   return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => 'Campanha não encontrada.'], 404);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error' => $error->getMessage()], 422);
  }
 }

 #[UseSession]
 #[NoAdminRequired]
 public function status(int $id, string $status = ''): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  try {
   $campaign = $this->campaigns->setStatus($id, $status);
   return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => 'Campanha não encontrada.'], 404);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error' => $error->getMessage()], 422);
  }
 }

 #[UseSession]
 #[NoAdminRequired]
 public function duplicate(int $id): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  $original = $this->campaigns->find($id);
  if ($original === null) { return new JSONResponse(['error' => 'Campanha não encontrada.'], 404); }
  $original['name'] = mb_substr($original['name'], 0, 145) . ' (cópia)';
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
   if ($uid === null) { return new JSONResponse(['error' => 'Sessão necessária.'], 401); }
   return new JSONResponse(['campaign' => $this->campaigns->create($original, $uid)], 201);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error' => $error->getMessage()], 422);
  }
 }

 #[UseSession]
 #[NoAdminRequired]
 public function move(int $id,string $direction='',?int $target_id=null):JSONResponse {
  if (($deny=$this->denied())!==null) {return $deny;}
  try {
   $items=$this->campaigns->move($id,$direction,$target_id);
   return $items===null
    ?new JSONResponse(['error'=>'Campanha não encontrada.'],404)
    :new JSONResponse(['order'=>array_map(static fn(array $row):int=>(int)$row['id'],$items)]);
  } catch (InvalidArgumentException $e) {
   return new JSONResponse(['error'=>$e->getMessage()],422);
  }
 }

 #[UseSession]
 #[NoAdminRequired]
 public function archive(int $id): JSONResponse {
  if (($deny = $this->denied()) !== null) { return $deny; }
  $campaign = $this->campaigns->setStatus($id, 'archived');
  return $campaign !== null ? new JSONResponse(['campaign' => $campaign]) : new JSONResponse(['error' => 'Campanha não encontrada.'], 404);
 }

 #[NoAdminRequired]
 #[UseSession]
 public function purge(int $id,string $confirmName=''):JSONResponse {
  if (($deny=$this->denied())!==null) { return $deny; }
  try {
   return $this->campaigns->purgeArchived($id,$confirmName)
    ? new JSONResponse(['deleted'=>true,'id'=>$id])
    : new JSONResponse(['error'=>'Publicação não encontrada.'],404);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error'=>$error->getMessage()],422);
  }
 }

 #[NoAdminRequired]
 #[NoCSRFRequired]
 #[UseSession]
 public function editorGroups(): JSONResponse {
  if (!$this->editorAccess->isAdmin($this->uid())) {
   return new JSONResponse(['error'=>'Somente administradores podem consultar a delegação.'],403);
  }
  return new JSONResponse(['groups'=>$this->editorAccess->groups()]);
 }

 #[NoAdminRequired]
 #[UseSession]
 public function setEditorGroups(): JSONResponse {
  if (!$this->editorAccess->isAdmin($this->uid())) {
   return new JSONResponse(['error'=>'Somente administradores podem delegar edição.'],403);
  }
  $groups = $this->request->getParam('groups',[]);
  if (!is_array($groups)) { return new JSONResponse(['error'=>'Lista de grupos inválida.'],422); }
  try {
   return new JSONResponse(['groups'=>$this->editorAccess->configure($this->uid(),$groups)]);
  } catch (InvalidArgumentException $error) {
   return new JSONResponse(['error'=>$error->getMessage()],422);
  }
 }
}
