<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Isolated NC36 lab fixture: substitute GatewayTransport for ALL external sends.
 */

require_once '/var/www/html/lib/base.php';

use OCA\Communications\Service\BoardPublicationService;
use OCA\Communications\Service\GatewayTransport;
use OCP\DB\QueryBuilder\IQueryBuilder;

class GatewayTransportStubV26 extends GatewayTransport {
	public array $messages = [];
	public function __construct() {
	}
	public function send(string $gateway, string $destination, string $body):void {
		$this->messages[] = ['gateway' => $gateway,'destination' => $destination,
			'hasLink' => str_contains($body, '/apps/communications/board')];
	}
}
$id = (int)($argv[1] ?? 0);
$version = (int)($argv[2] ?? 0);
if ($id <= 0 || $version <= 0) {
	throw new RuntimeException('Fixture requires test campaign and version');
}
$server = \OC::$server;
$store = $server->get(BoardPublicationService::class);
$fake = new GatewayTransportStubV26();
(new ReflectionProperty(BoardPublicationService::class, 'gatewayTransport'))->setValue($store, $fake);
(new ReflectionMethod(BoardPublicationService::class, 'ensurePublication'))->invoke($store, $id, $version);
$first = $store->broadcast($id, $version);
$second = $store->broadcast($id, $version);

$db = $server->get(\OCP\IDBConnection::class);
$qb = $db->getQueryBuilder();
$rs = $qb->select('uid', 'channel', 'status', 'attempts', 'last_error')->from('cc_board_sent')
	->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
	->andWhere($qb->expr()->eq('version', $qb->createNamedParameter($version, IQueryBuilder::PARAM_INT)))
	->executeQuery();
$rows = $rs->fetchAllAssociative();
$rs->closeCursor();
echo json_encode(['messages' => $fake->messages, 'rows' => $rows, 'first' => $first,'second' => $second],
	JSON_THROW_ON_ERROR);
