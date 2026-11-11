<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Controller;
use OCA\Communications\Service\CampaignRepository;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/** Anonymous read-only projection; never deliver private audience data. */
final class GuestBannerController extends Controller {
 public function __construct(IRequest $request,private CampaignRepository $campaigns) {
  parent::__construct('communications',$request);
 }

 #[PublicPage]
 #[NoCSRFRequired]
 public function index():JSONResponse {
  $r=new JSONResponse(['banners'=>$this->campaigns->publicForGuests(time())]);
  $r->addHeader('Cache-Control','private, no-store, max-age=0');
  $r->addHeader('X-Content-Type-Options','nosniff');
  return $r;
 }
}
