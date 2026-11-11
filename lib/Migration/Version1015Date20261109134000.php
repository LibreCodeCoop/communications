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

/** Optional gateway IDs and longer outbox channel keys, preserving all rows. */
class Version1015Date20261109134000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options): ?ISchemaWrapper {
  $schema=$schemaClosure();
  $changed=false;
  if ($schema->hasTable('cc_campaigns')) {
   $table=$schema->getTable('cc_campaigns');
   if (!$table->hasColumn('gateway_channels')) {
    $table->addColumn('gateway_channels',Types::TEXT,['notnull'=>true,'default'=>'[]']);
    $changed=true;
   }
  }
  if ($schema->hasTable('cc_board_sent')) {
   $table=$schema->getTable('cc_board_sent');
   if ($table->hasColumn('channel') && $table->getColumn('channel')->getLength()<32) {
    $table->changeColumn('channel',['length'=>32]);
    $changed=true;
   }
  }
  return $changed?$schema:null;
 }
}
