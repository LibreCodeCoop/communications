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

/** Exclusion AND semantics; existing campaigns continue to exclude ANY. */
class Version1012Date20261108234000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if(!$schema->hasTable('cc_campaigns'))return null;
  $table=$schema->getTable('cc_campaigns');
  if($table->hasColumn('excluded_match'))return null;
  $table->addColumn('excluded_match',Types::STRING,['length'=>8,'notnull'=>true,'default'=>'any']);
  return $schema;
 }
}
