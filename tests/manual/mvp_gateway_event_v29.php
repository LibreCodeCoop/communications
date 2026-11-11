<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later
 * Inject a local fake: never calls Two-Factor Gateway or third-party services.
 */
require '/var/www/html/lib/base.php';
use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\GatewayTransport;
use OCP\DB\QueryBuilder\IQueryBuilder;

class MVPGatewayStubV29 extends GatewayTransport {
 public array $messages=[];
 public function __construct() {}
 public function send(string $gateway,string $destination,string $body):void {
  $this->messages[]=[
   'gateway'=>$gateway,
   'destination'=>$destination,
   'linked'=>str_contains($body,'/apps/communications/board?id='),
  ];
 }
}
$id=(int)($argv[1]??0);
$eventId=(int)($argv[2]??0);
$c=\OC::$server->get(\OCA\Communications\Service\CampaignRepository::class)->find($id);
$event=\OC::$server->get(\OCA\Communications\Service\EventStore::class)->byId($eventId);
if (!$c || !$event || !str_starts_with($c['name'],'MVP Gateway v29 ')
   || $c['trigger_key']!=='external.webhook'
   || $event['context_app']!=='campaign_'.$id || $event['uid']!=='poc_second') {
 throw new \InvalidArgumentException('Refusing to invoke non-isolated recipient event');
}
$publisher=\OC::$server->get(BoardPublicationService::class);
$stub=new MVPGatewayStubV29();
(new ReflectionProperty(BoardPublicationService::class,'gatewayTransport'))->setValue($publisher,$stub);
$first=$publisher->dispatchEvent($eventId);
$second=$publisher->dispatchEvent($eventId);
$db=\OC::$server->get(\OCP\IDBConnection::class);
$q=$db->getQueryBuilder();
$rs=$q->select('event_id','uid','channel','status','attempts')->from('cc_board_sent')
 ->where($q->expr()->eq('campaign_id',$q->createNamedParameter($id,IQueryBuilder::PARAM_INT)))
 ->executeQuery();
$rows=$rs->fetchAllAssociative();$rs->closeCursor();
echo json_encode(['messages'=>$stub->messages,'rows'=>$rows,'first'=>$first,'second'=>$second],JSON_THROW_ON_ERROR);
