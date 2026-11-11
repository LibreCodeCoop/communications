<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
/**
 * Native Nextcloud 36 integration test: no real user URLs persisted.
 * Execute with Nextcloud bootstrap preloaded inside the isolated container.
 */
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EventStore;
use OCA\Communications\Service\WebRouteCatalog;

$server = \OC::$server;
$catalog = $server->get(WebRouteCatalog::class);
$repo = $server->get(CampaignRepository::class);
$deliveries = $server->get(DeliveryStore::class);
$events = $server->get(EventStore::class);
$uid = 'poc_second';

function check(bool $ok,string $message): void {
 echo ($ok?'PASS':'FAIL') . ' | ' . $message . "\n";
 if (!$ok) { throw new RuntimeException($message); }
}
check(count($catalog->routesFor('dashboard'))>0,'Dashboard discovered from FrontpageRoute attribute');
$files=$catalog->routesFor('files');
check(count($files)>=2,'Files has distinct browser routes');
check(!in_array('/apps/files/api/v1/stats',array_column($files,'value'),true),'API route not exposed');
check($catalog->matches('/apps/files/{view}','files','/index.php/apps/files/recent'), 'route placeholder recognized');
check(!$catalog->matches('/apps/files/{view}','files','/index.php/apps/files/api/v1/stats'), 'API page rejected');

$data=[
 'name'=>'Route matching proof '.bin2hex(random_bytes(4)),
 'description'=>'Automated test, deleted immediately',
 'headline'=>'Only on a specific web page',
 'message'=>'Tested route routing',
 'cta_label'=>'','cta_url'=>'',
 'trigger_key'=>'nextcloud.page.opened',
 'page_route'=>'/apps/files/{view}',
 'placement'=>'files',
 'audience_type'=>'users','audience_users'=>[$uid],
 'audience_groups'=>[],'audience_group'=>'','excluded_groups'=>[],
 'presentation'=>'banner','status'=>'active','priority'=>100,
 'starts_at'=>null,'ends_at'=>null,'frequency'=>'event',
 'max_total'=>0,'max_per_day'=>0,
];
try {
 $invalid=$data;
 $invalid['page_route']='/apps/files/api/v1/stats';
 $thrown=false;
 try {$repo->validate($invalid);}catch(InvalidArgumentException){$thrown=true;}
 check($thrown,'Backend refuses an API endpoint as campaign route');
 $campaign=$repo->create($data,'poc_admin');
 $id=$campaign['id'];
 try {
  check($campaign['page_route']==='/apps/files/{view}','Saved route pattern without actual URL');
  $eventFor=static fn():array => array_values(array_filter(
   $events->forUser($uid,time()-60,300),
   static fn(array $event):bool => $event['trigger_key']==='nextcloud.page.opened'
      && $event['context_app']==='campaign_'.$id,
  ));
  $deliveries->prepare($uid,'files',true,'/index.php/apps/files/api/v1/stats');
  check(count($eventFor())===0,'API call does not generate a page event');
  $deliveries->prepare($uid,'dashboard',true,'/index.php/apps/dashboard/');
  check(count($eventFor())===0,'Opening Dashboard does not trigger Files route');
  $deliveries->prepare($uid,'files',true,'/index.php/apps/files/');
  check(count($eventFor())===0,'Files root does not match files/{view}');
  $deliveries->prepare($uid,'files',true,'/index.php/apps/files/recent');
  check(count($eventFor())===1,'Actual page visit creates scoped event for selected campaign only');
  $pending=$deliveries->pending($uid,'files',250,'/index.php/apps/files/recent');
  check(count(array_filter($pending,static fn(array $item):bool=>$item['campaignId']===$id))===1,
   'Only matching route creates a campaign delivery');
  $pendingWrong=$deliveries->pending($uid,'files',250,'/index.php/apps/files/');
  check(count(array_filter($pendingWrong,static fn(array $item):bool=>$item['campaignId']===$id))===0,
   'Pending campaign cannot be shown on other routes');
  $subject=$eventFor()[0];
  check($subject['context_app']==='campaign_'.$id,'Event contains no path or private file ID');
  echo 'PAGE_ROUTE_E2E PASSED'."\n";
 }finally{
  $repo->setStatus((int)$id,'archived');
  echo 'CLEANUP archived campaign '.$id."\n";
 }
} catch(Throwable $e) {
 fwrite(STDERR, 'PAGE_ROUTE_E2E FAILED: '.$e->getMessage()."\n");
 throw $e;
}
