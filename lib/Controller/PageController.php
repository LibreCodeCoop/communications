<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUserSession;
use OCA\Communications\Service\EditorAccess;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UseSession;
use OCP\IRequest;
use OCP\Util;

class PageController extends Controller {
 public function __construct(IRequest $request, private IUserSession $session,private EditorAccess $editorAccess) { parent::__construct('communications', $request); }

 #[NoAdminRequired]
 #[NoCSRFRequired]
 #[UseSession]
 public function index(): Response {
  return $this->renderHub();
 }

 #[NoAdminRequired]
 #[NoCSRFRequired]
 #[UseSession]
 public function board(): TemplateResponse {
  return $this->renderHub();
 }

 private function renderHub(): TemplateResponse {
  Util::addScript('communications', 'management');
  Util::addStyle('communications', 'management');
  return new TemplateResponse('communications','main',[
   'canEdit'=>$this->editorAccess->canEdit($this->session->getUser()?->getUID()),
  ]);
 }
}
