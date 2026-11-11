<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later
 * Inspect isolated MVP test artifacts without exposing unrelated user data.
 */
fwrite(STDERR,'PROBE_START\n');
require '/var/www/html/lib/base.php';
fwrite(STDERR,'PROBE_AFTER_BASE\n');

use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\CampaignRepository;
use OCP\DB\QueryBuilder\IQueryBuilder;

try {
$id=(int)($argv[1]??0);
if ($id<=0) {throw new \InvalidArgumentException('Missing fixture campaign ID');}
$server=\OC::$server;
$repo=$server->get(CampaignRepository::class);
$c=$repo->find($id);
fwrite(STDERR,'PROBE_FIND '.json_encode($c===null).'\n');
if (!$c || !str_starts_with($c['name'],'MVP Real v29 ')) {
 throw new \InvalidArgumentException('Refusing to inspect non-fixture campaign');
}
fwrite(STDERR,'PROBE_AFTER_CHECK\n');
$db=$server->get(\OCP\IDBConnection::class);
$qb=$db->getQueryBuilder();
$rs=$qb->select('id','event_id','uid','channel','status','attempts','last_error','version','sent_at')
 ->from('cc_board_sent')
 ->where($qb->expr()->eq('campaign_id',$qb->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
 ->orderBy('id','ASC')->executeQuery();
$rows=$rs->fetchAllAssociative();$rs->closeCursor();
$eventId=null;
$q=$db->getQueryBuilder();
$r=$q->select('id')->from('cc_events')
 ->where($q->expr()->eq('uid',$q->createNamedParameter('poc_second')))
 ->andWhere($q->expr()->eq('trigger_key',$q->createNamedParameter($c['trigger_key'])))
 ->andWhere($q->expr()->eq('context_app',$q->createNamedParameter('campaign_'.$id)))
 ->orderBy('id','DESC')->setMaxResults(1)->executeQuery();
$value=$r->fetchOne();$r->closeCursor();
if ($value!==false) {$eventId=(int)$value;}
$jobs=[];
$q=$db->getQueryBuilder();
$r=$q->select('id','class','argument')->from('jobs')
 ->where($q->expr()->like('class',$q->createNamedParameter('%EventChannelDeliveryJob%')))
 ->executeQuery();
foreach ($r->fetchAllAssociative() as $job) {
 $decoded=json_decode($job['argument'],true);
 if (is_array($decoded) && is_int($decoded['eventId']??null)) {
  $jobs[]=['jobId'=>(int)$job['id'],'eventId'=>$decoded['eventId']];
 }
}
$r->closeCursor();
$records=[];
foreach (['notifications','activity'] as $table) {
 $q=$db->getQueryBuilder();
 $rs=$q->select('*')->from($table)
  ->where($q->expr()->eq('app',$q->createNamedParameter('communications')))
  ->setMaxResults(100)->executeQuery();
 $rowsForApp=$rs->fetchAllAssociative();$rs->closeCursor();
 foreach ($rowsForApp as $entry) {
  $objectId=$entry['object_id']??$entry['objectid']??null;
  if ((string)$objectId===(string)$id) {
   $records[$table][]=[
    'uid'=>$entry['user']??$entry['affecteduser']??$entry['affected_user']??null,
    'type'=>$entry['object_type']??$entry['type']??null,
    'id'=>(int)$entry['id'],
   ];
  }
 }
}
$board=$server->get(\OCA\Communications\Service\BoardService::class);
$inbox=$board->list('poc_second',1,$c['headline'],'all');
$view=$board->view('poc_second',$id);
$inboxFound=false;foreach($inbox['announcements'] as $item){if($item['id']===$id){$inboxFound=true;break;}}
echo json_encode([
 'campaignId'=>$id,'eventId'=>$eventId,'publishedAt'=>(int)$c['published_at'],
 'outbox'=>$rows,'jobs'=>$jobs,'records'=>$records,
 'inboxFound'=>$inboxFound,
 'viewAllowed'=>$view!==null,
],JSON_THROW_ON_ERROR);

} catch (\Throwable $error) {
 fwrite(STDERR,"PROBE_EXCEPTION ".get_class($error)." ".$error->getMessage()."\n");
 exit(2);
}
