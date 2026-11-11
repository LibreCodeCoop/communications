<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
/** Run inside NC36 after lib/base.php was already included. */
use OCA\Communications\Service\CampaignRepository;
use OCA\Communications\Service\DeliveryStore;
use OCA\Communications\Service\EventStore;
$repo = \OC::$server->get(CampaignRepository::class);
$events = \OC::$server->get(EventStore::class);
$store = \OC::$server->get(DeliveryStore::class);
$stateFile = '/tmp/cc-scoped-webhook-test.json';
$cmd = $argv[1] ?? '';
if ($cmd === 'create') {
 $data = [
  'name'=>'Webhook targeting proof '.bin2hex(random_bytes(3)),
  'description'=>'Ephemeral API target validation test',
  'headline'=>'Webhook targeted only this campaign',
  'message'=>'Signed webhook integration example',
  'cta_label'=>'','cta_url'=>'',
  'trigger_key'=>'external.webhook', 'status'=>'active', 'placement'=>'any',
  'audience_type'=>'users','audience_users'=>['poc_second'],
  'audience_group'=>'','audience_groups'=>[],
  'presentation'=>'banner','priority'=>100,
 ];
 $a = $repo->create($data,'poc_admin');
 $data['name']='Webhook unrelated '.bin2hex(random_bytes(3));
 $data['headline']='Must not be triggered';
 $b = $repo->create($data,'poc_admin');
 $state=['a'=>$a['id'],'b'=>$b['id']];
 file_put_contents($stateFile,json_encode($state,JSON_THROW_ON_ERROR));
 echo 'WEBHOOK_TEST_IDS '.json_encode($state)."\n";
} elseif ($cmd === 'verify') {
 $ids=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
 $contextA='campaign_'.$ids['a']; $contextB='campaign_'.$ids['b'];
 $recent=$events->forUser('poc_second',time()-120,300);
 $aEvents=array_filter($recent,static fn($event):bool=>$event['trigger_key']==='external.webhook' && $event['context_app']===$contextA);
 $bEvents=array_filter($recent,static fn($event):bool=>$event['trigger_key']==='external.webhook' && $event['context_app']===$contextB);
 $store->prepare('poc_second','files',false);
 $pending=$store->pending('poc_second','files',250);
 $aPending=array_filter($pending,static fn($item):bool=>$item['campaignId']===$ids['a']);
 $bPending=array_filter($pending,static fn($item):bool=>$item['campaignId']===$ids['b']);
 $ok=count($aEvents)===1 && count($bEvents)===0 && count($aPending)===1 && count($bPending)===0;
 echo ($ok?'PASS':'FAIL').' | Signed webhook scoped to one UID and one campaign | '.json_encode([
  'targetEvents'=>count($aEvents), 'otherEvents'=>count($bEvents),
  'targetDeliveries'=>count($aPending), 'otherDeliveries'=>count($bPending)
 ])."\n";
 exit($ok?0:2);
} elseif ($cmd === 'cleanup') {
 if(is_file($stateFile)){
  $ids=json_decode(file_get_contents($stateFile),true,512,JSON_THROW_ON_ERROR);
  $repo->setStatus((int)$ids['a'],'archived');
  $repo->setStatus((int)$ids['b'],'archived');
  unlink($stateFile);
  echo 'WEBHOOK_TEST_CLEANUP completed'."\n";
 }
} else {throw new RuntimeException('Usage create|verify|cleanup');}
