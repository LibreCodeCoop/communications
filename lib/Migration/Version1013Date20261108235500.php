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

/** Guest publication is explicitly opt-in, disabled for all previous rows. */
class Version1013Date20261108235500 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if(!$schema->hasTable('cc_campaigns'))return null;
  $table=$schema->getTable('cc_campaigns');
  if($table->hasColumn('guest_visible'))return null;
  $table->addColumn('guest_visible',Types::SMALLINT,['notnull'=>true,'default'=>0]);
  $table->addIndex(['guest_visible','status'],'cc_guest_status');
  return $schema;
 }
}
