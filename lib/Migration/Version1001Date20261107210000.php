<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Add multiple group/user targets without rewriting legacy campaign records.
 */
namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1001Date20261107210000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table = $schema->getTable('cc_campaigns');
  $changed = false;
  if (!$table->hasColumn('audience_groups')) {
   $table->addColumn('audience_groups', Types::TEXT, ['notnull' => true, 'default' => '[]']);
   $changed = true;
  }
  if (!$table->hasColumn('audience_users')) {
   $table->addColumn('audience_users', Types::TEXT, ['notnull' => true, 'default' => '[]']);
   $changed = true;
  }
  return $changed ? $schema : null;
 }
}
