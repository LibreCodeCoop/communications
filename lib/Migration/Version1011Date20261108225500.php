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

/** Per-user inbox state, separate from estimated browser impressions. */
class Version1011Date20261108225500 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if ($schema->hasTable('cc_inbox_reads')) { return null; }
  $table=$schema->createTable('cc_inbox_reads');
  $table->addColumn('uid',Types::STRING,['length'=>64,'notnull'=>true]);
  $table->addColumn('campaign_id',Types::BIGINT,['notnull'=>true]);
  $table->addColumn('read_at',Types::BIGINT,['notnull'=>true]);
  $table->setPrimaryKey(['uid','campaign_id']);
  return $schema;
 }
}
