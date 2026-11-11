<?php
/** SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors */
declare(strict_types=1);
/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Durable events and per-user campaign lifecycle. Additive migration.
 */
namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1002Date20261107221500 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  $changed = false;
  if (!$schema->hasTable('cc_events')) {
   $events = $schema->createTable('cc_events');
   $events->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
   $events->addColumn('event_uuid', Types::STRING, ['length' => 36, 'notnull' => true]);
   $events->addColumn('dedupe_key', Types::STRING, ['length' => 64, 'notnull' => true]);
   $events->addColumn('trigger_key', Types::STRING, ['length' => 64, 'notnull' => true]);
   $events->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
   $events->addColumn('context_app', Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'any']);
   $events->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
   $events->setPrimaryKey(['id']);
   $events->addUniqueIndex(['event_uuid'], 'cc_event_uuid');
   $events->addUniqueIndex(['dedupe_key'], 'cc_event_dedupe');
   $events->addIndex(['uid', 'created_at'], 'cc_event_uid_time');
   $changed = true;
  }
  if (!$schema->hasTable('cc_deliveries')) {
   $delivery = $schema->createTable('cc_deliveries');
   $delivery->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true]);
   $delivery->addColumn('event_id', Types::BIGINT, ['notnull' => true]);
   $delivery->addColumn('campaign_id', Types::BIGINT, ['notnull' => true]);
   $delivery->addColumn('uid', Types::STRING, ['length' => 64, 'notnull' => true]);
   $delivery->addColumn('state', Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']);
   $delivery->addColumn('claim_token', Types::STRING, ['length' => 48, 'notnull' => true, 'default' => '']);
   $delivery->addColumn('claim_until', Types::BIGINT, ['notnull' => true, 'default' => 0]);
   $delivery->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
   $delivery->addColumn('impression_at', Types::BIGINT, ['notnull' => false]);
   $delivery->addColumn('dismissed_at', Types::BIGINT, ['notnull' => false]);
   $delivery->addColumn('clicked_at', Types::BIGINT, ['notnull' => false]);
   $delivery->setPrimaryKey(['id']);
   $delivery->addUniqueIndex(['event_id', 'campaign_id'], 'cc_delivery_event_campaign');
   $delivery->addIndex(['uid', 'state'], 'cc_delivery_uid_state');
   $delivery->addIndex(['campaign_id', 'uid', 'impression_at'], 'cc_delivery_frequency');
   $changed = true;
  }
  if ($schema->hasTable('cc_campaigns')) {
   $campaign = $schema->getTable('cc_campaigns');
   $columns = [
    'placement' => [Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'any']],
    'frequency' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'event']],
    'min_interval_minutes' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
    'max_total' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
    'max_per_day' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
    'category' => [Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'institutional']],
    'group_match' => [Types::STRING, ['length' => 8, 'notnull' => true, 'default' => 'any']],
    'excluded_groups' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
    'theme' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'default']],
   ];
   foreach ($columns as $name => [$type, $opts]) {
    if (!$campaign->hasColumn($name)) {
     $campaign->addColumn($name, $type, $opts);
     $changed = true;
    }
   }
  }
  return $changed ? $schema : null;
 }
}
