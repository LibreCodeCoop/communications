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

/** Add multiple-app include/exclude targeting without changing legacy campaigns. */
final class Version1010Date20261108220000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table=$schema->getTable('cc_campaigns');
  $changed=false;
  if (!$table->hasColumn('app_target_mode')) {
   $table->addColumn('app_target_mode',Types::STRING,['notnull'=>true,'length'=>12,'default'=>'legacy']);
   $changed=true;
  }
  if (!$table->hasColumn('app_target_apps')) {
   $table->addColumn('app_target_apps',Types::TEXT,['notnull'=>true,'default'=>'[]']);
   $changed=true;
  }
  return $changed?$schema:null;
 }
}
