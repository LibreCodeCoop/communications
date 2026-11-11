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

/** Adds a permanent announcement wall without changing existing campaign delivery. */
class Version1008Date20261108204500 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  $changed = false;
  if ($schema->hasTable('cc_campaigns')) {
   $campaign = $schema->getTable('cc_campaigns');
   $fields = [
    'board_visible' => [Types::SMALLINT, ['notnull'=>true,'default'=>0]],
    'comments_enabled' => [Types::SMALLINT, ['notnull'=>true,'default'=>1]],
    'dismissible' => [Types::SMALLINT, ['notnull'=>true,'default'=>1]],
    'text_align' => [Types::STRING, ['length'=>8,'notnull'=>true,'default'=>'left']],
    'text_color' => [Types::STRING, ['length'=>7,'notnull'=>false]],
    'manual_order' => [Types::INTEGER, ['notnull'=>true,'default'=>0]],
    'translated_content' => [Types::TEXT, ['notnull'=>true,'default'=>'{}']],
   ];
   foreach ($fields as $name => [$type, $options]) {
    if (!$campaign->hasColumn($name)) {
     $campaign->addColumn($name, $type, $options);
     $changed = true;
    }
   }
  }
  if (!$schema->hasTable('cc_board_comments')) {
   $comments = $schema->createTable('cc_board_comments');
   $comments->addColumn('id', Types::BIGINT, ['autoincrement'=>true,'notnull'=>true]);
   $comments->addColumn('campaign_id', Types::BIGINT, ['notnull'=>true]);
   $comments->addColumn('uid', Types::STRING, ['length'=>64,'notnull'=>true]);
   $comments->addColumn('message', Types::TEXT, ['notnull'=>true]);
   $comments->addColumn('created_at', Types::BIGINT, ['notnull'=>true]);
   $comments->addColumn('updated_at', Types::BIGINT, ['notnull'=>true]);
   $comments->addColumn('deleted_at', Types::BIGINT, ['notnull'=>false]);
   $comments->setPrimaryKey(['id']);
   $comments->addIndex(['campaign_id','id'], 'cc_board_comments_campaign');
   $comments->addIndex(['uid','created_at'], 'cc_board_comments_uid');
   $changed = true;
  }
  return $changed ? $schema : null;
 }
}
