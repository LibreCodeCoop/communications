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

/**
 * One durable outbox for both bulk publications and individual event delivery.
 * Historical rows use event_id=0, preserving the old unique send identity.
 */
final class Version1016Date20261109150000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output,Closure $schemaClosure,array $options):?ISchemaWrapper {
  $schema=$schemaClosure();
  if (!$schema->hasTable('cc_board_sent')) { return null; }
  $table=$schema->getTable('cc_board_sent');
  if ($table->hasColumn('event_id')) { return null; }
  $table->addColumn('event_id',Types::BIGINT,['notnull'=>true,'default'=>0]);
  if ($table->hasIndex('cc_board_sent_unique')) {
   $table->dropIndex('cc_board_sent_unique');
  }
  $table->addUniqueIndex(
   ['campaign_id','version','event_id','uid','channel'],
   'cc_sent_occ_unique'
  );
  $table->addIndex(['event_id','status','claim_until'],'cc_sent_event_retry');
  $table->addIndex(['uid','channel','status'],'cc_sent_user_channel');
  return $schema;
 }
}
