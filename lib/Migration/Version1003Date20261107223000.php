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

class Version1003Date20261107223000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if ($schema->hasTable('cc_leases')) { return null; }
  $table = $schema->createTable('cc_leases');
  $table->addColumn('id', Types::BIGINT, ['autoincrement'=>true, 'notnull'=>true]);
  $table->addColumn('uid', Types::STRING, ['length'=>64, 'notnull'=>true]);
  $table->addColumn('campaign_id', Types::BIGINT, ['notnull'=>true]);
  $table->addColumn('delivery_id', Types::BIGINT, ['notnull'=>true, 'default'=>0]);
  $table->addColumn('token', Types::STRING, ['length'=>48, 'notnull'=>true, 'default'=>'']);
  $table->addColumn('expires_at', Types::BIGINT, ['notnull'=>true, 'default'=>0]);
  $table->setPrimaryKey(['id']);
  $table->addUniqueIndex(['uid','campaign_id'], 'cc_lease_uid_campaign');
  return $schema;
 }
}
