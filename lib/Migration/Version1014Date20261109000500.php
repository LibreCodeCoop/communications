<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/** SPDX-License-Identifier: AGPL-3.0-or-later */
namespace OCA\Communications\Migration;
use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Support #RRGGBBAA with non-destructive column expansion. */
class Version1014Date20261109000500 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if(!$schema->hasTable('cc_campaigns'))return null;
  $table=$schema->getTable('cc_campaigns');
  if($table->hasColumn('banner_color'))$table->changeColumn('banner_color',['length'=>9]);
  if($table->hasColumn('text_color'))$table->changeColumn('text_color',['length'=>9]);
  return $schema;
 }
}
