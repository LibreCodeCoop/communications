<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later
 * Isolated Nextcloud 36 E2E fixture. No external transports are invoked.
 */
require_once '/var/www/html/lib/base.php';

use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;

$id=(int)($argv[1]??0);
$uid=$argv[2]??'poc_second';
$c=\OC::$server->get(CampaignRepository::class)->find($id);
if (!$c || !str_starts_with($c['name'],'Unified V28 ')
 || $c['trigger_key']!=='nextcloud.board.published'
 || !in_array($c['presentation'],['banner','modal'],true)) {
 throw new \InvalidArgumentException('Only isolated immediate visual fixtures allowed');
}
$store=\OC::$server->get(DeliveryStore::class);
$store->prepare($uid,'files',true);
$one=array_values(array_filter($store->pending($uid,'files',100),
 static fn(array $item):bool=>$item['campaignId']===$id));
$store->prepare($uid,'files',true);
$two=array_values(array_filter($store->pending($uid,'files',100),
 static fn(array $item):bool=>$item['campaignId']===$id));
echo json_encode([
 'id'=>$id, 'presentation'=>$c['presentation'],
 'first'=>count($one),'second'=>count($two),
 'stable'=>$one!==[] && $two!==[]
  && $one[0]['deliveryId']===$two[0]['deliveryId'],
 'title'=>$one[0]['title']??null,
 'inbox'=>$c['board_visible'],'email'=>$c['notify_email'],
],JSON_THROW_ON_ERROR);
