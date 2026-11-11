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

/** Safe opt-in publication channels and idempotent per-user fanout. */
class Version1009Date20261108213000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  $changed = false;
  if ($schema->hasTable('cc_campaigns')) {
   $campaign = $schema->getTable('cc_campaigns');
   foreach (['notify_users','notify_activity','notify_email'] as $name) {
    if (!$campaign->hasColumn($name)) {
     $campaign->addColumn($name, Types::SMALLINT, ['notnull'=>true,'default'=>0]);
     $changed = true;
    }
   }
  }
  if (!$schema->hasTable('cc_board_pub')) {
   $table = $schema->createTable('cc_board_pub');
   $table->addColumn('id', Types::BIGINT, ['autoincrement'=>true,'notnull'=>true]);
   $table->addColumn('campaign_id', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('version', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('event_sent', Types::SMALLINT, ['notnull'=>true,'default'=>0]);
   $table->addColumn('broadcast_done', Types::SMALLINT, ['notnull'=>true,'default'=>0]);
   $table->addColumn('created_at', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('event_sent_at', Types::BIGINT, ['notnull'=>false]);
   $table->addColumn('last_error', Types::STRING, ['length'=>250,'notnull'=>false]);
   $table->setPrimaryKey(['id']);
   $table->addUniqueIndex(['campaign_id','version'],'cc_board_pub_unique');
   $table->addIndex(['event_sent','created_at'],'cc_board_pub_event');
   $changed = true;
  }
  if (!$schema->hasTable('cc_board_sent')) {
   $table = $schema->createTable('cc_board_sent');
   $table->addColumn('id', Types::BIGINT, ['autoincrement'=>true,'notnull'=>true]);
   $table->addColumn('campaign_id', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('version', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('uid', Types::STRING, ['length'=>64,'notnull'=>true]);
   $table->addColumn('channel', Types::STRING, ['length'=>16,'notnull'=>true]);
   $table->addColumn('status', Types::STRING, ['length'=>12,'notnull'=>true,'default'=>'pending']);
   $table->addColumn('created_at', Types::BIGINT, ['notnull'=>true]);
   $table->addColumn('sent_at', Types::BIGINT, ['notnull'=>false]);
   $table->addColumn('attempts', Types::INTEGER, ['notnull'=>true,'default'=>0]);
   $table->addColumn('claim_until', Types::BIGINT, ['notnull'=>true,'default'=>0]);
   $table->addColumn('last_error', Types::STRING, ['length'=>250,'notnull'=>false]);
   $table->setPrimaryKey(['id']);
   $table->addUniqueIndex(['campaign_id','version','uid','channel'],'cc_board_sent_unique');
   $table->addIndex(['status','claim_until'],'cc_board_sent_claim');
   $changed = true;
  }
  return $changed ? $schema : null;
 }
}
