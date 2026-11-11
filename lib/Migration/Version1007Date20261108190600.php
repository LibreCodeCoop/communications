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

/** Optional appearance metadata; additive and non-destructive for old rows. */
class Version1007Date20261108190600 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table = $schema->getTable('cc_campaigns');
  $changed = false;
  if (!$table->hasColumn('banner_color')) {
   $table->addColumn('banner_color', Types::STRING, [
    'length' => 7, 'notnull' => true, 'default' => '#6748AC'
   ]);
   $changed = true;
  }
  if (!$table->hasColumn('banner_icon')) {
   $table->addColumn('banner_icon', Types::STRING, [
    'length' => 12, 'notnull' => true, 'default' => 'auto'
   ]);
   $changed = true;
  }
  return $changed ? $schema : null;
 }
}
