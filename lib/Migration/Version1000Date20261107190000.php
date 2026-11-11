<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20261107190000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if ($schema->hasTable('cc_campaigns')) {
   return null;
  }
  $table = $schema->createTable('cc_campaigns');
  $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
  $table->addColumn('name', Types::STRING, ['length' => 160, 'notnull' => true]);
  $table->addColumn('description', Types::TEXT, ['notnull' => true, 'default' => '']);
  $table->addColumn('headline', Types::STRING, ['length' => 180, 'notnull' => true]);
  $table->addColumn('message', Types::TEXT, ['notnull' => true]);
  $table->addColumn('cta_label', Types::STRING, ['length' => 80, 'notnull' => true, 'default' => '']);
  $table->addColumn('cta_url', Types::STRING, ['length' => 2000, 'notnull' => true, 'default' => '']);
  $table->addColumn('trigger_key', Types::STRING, ['length' => 64, 'notnull' => true]);
  $table->addColumn('audience_type', Types::STRING, ['length' => 20, 'notnull' => true, 'default' => 'all']);
  $table->addColumn('audience_group', Types::STRING, ['length' => 64, 'notnull' => true, 'default' => '']);
  $table->addColumn('presentation', Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'banner']);
  $table->addColumn('status', Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'draft']);
  $table->addColumn('priority', Types::INTEGER, ['notnull' => true, 'default' => 10]);
  $table->addColumn('starts_at', Types::BIGINT, ['notnull' => false]);
  $table->addColumn('ends_at', Types::BIGINT, ['notnull' => false]);
  $table->addColumn('created_by', Types::STRING, ['length' => 64, 'notnull' => true]);
  $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
  $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
  $table->setPrimaryKey(['id']);
  $table->addIndex(['status', 'trigger_key'], 'cc_status_trigger');
  return $schema;
 }
}
