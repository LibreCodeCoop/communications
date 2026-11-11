<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Prevent republishing a paused/draft campaign from replaying old events.
 * Existing active campaigns start a fresh effective publication window.
 */
class Version1004Date20261107230000 extends SimpleMigrationStep {
 public function __construct(private IDBConnection $db) {}

 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table = $schema->getTable('cc_campaigns');
  if ($table->hasColumn('published_at')) { return null; }
  $table->addColumn('published_at', Types::BIGINT, ['notnull'=>true,'default'=>0]);
  return $schema;
 }

 public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
  $now = time();
  $qb = $this->db->getQueryBuilder();
  $qb->update('cc_campaigns')
   ->set('published_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_INT))
   ->where($qb->expr()->eq('status', $qb->createNamedParameter('active')))
   ->andWhere($qb->expr()->eq('published_at', $qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
   ->executeStatement();
 }
}
