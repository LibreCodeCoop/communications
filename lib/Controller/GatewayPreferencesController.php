<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Controller;

use OCA\Communications\Service\GatewayRecipientPreferences;
use OCA\Communications\Service\GatewayTransport;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

final class GatewayPreferencesController extends Controller {
 public function __construct(
  IRequest $request,
  private IUserSession $session,
  private GatewayRecipientPreferences $preferences,
  private GatewayTransport $gateway,
 ) { parent::__construct('communications',$request); }

 private function uid(): ?string { return $this->session->getUser()?->getUID(); }

 /** Only the logged-in user sees their own destinations. */
 #[NoAdminRequired]
 #[NoCSRFRequired]
 #[UseSession]
 public function index(): JSONResponse {
  $uid=$this->uid();
  if ($uid===null) { return new JSONResponse(['error'=>'Login necessário.'],401); }
  $saved=$this->preferences->forUser($uid);
  $channels=[];
  foreach (GatewayTransport::LABELS as $id=>$label) {
   $channels[]=[
    'id'=>$id,'label'=>$label,
    'available'=>$this->gateway->configured($id),
    'installed'=>$this->gateway->installed(),
    'enabled'=>isset($saved[$id]),
    'destination'=>$saved[$id]['destination']??'',
    'sponsored'=>$saved[$id]['sponsored']??false,
   ];
  }
  return new JSONResponse(['channels'=>$channels]);
 }

 /** Full replacement: disabled channels lose their stored destinations. */
 #[NoAdminRequired]
 #[UseSession]
 public function update(array $channels=[]): JSONResponse {
  $uid=$this->uid();
  if ($uid===null) { return new JSONResponse(['error'=>'Login necessário.'],401); }
  try {
   $this->preferences->replace($uid,$channels);
   return $this->index();
  } catch (\InvalidArgumentException $error) {
   return new JSONResponse(['error'=>$error->getMessage()],422);
  }
 }
}
