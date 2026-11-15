<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Initial installation schema for the unreleased Communications application. */
final class Version1000Date20261107190000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$definitions = [
			'cc_campaigns' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'app_target_apps' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
					'app_target_mode' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'area']],
					'audience_groups' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
					'audience_type' => [Types::STRING, ['length' => 20, 'notnull' => true, 'default' => 'all']],
					'audience_users' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
					'banner_color' => [Types::STRING, ['length' => 9, 'notnull' => true, 'default' => '#6748AC']],
					'banner_icon' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'auto']],
					'board_visible' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'category' => [Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'institutional']],
					'comments_enabled' => [Types::SMALLINT, ['notnull' => true, 'default' => 1]],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'created_by' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'cta_label' => [Types::STRING, ['length' => 80, 'notnull' => true, 'default' => '']],
					'cta_url' => [Types::STRING, ['length' => 2000, 'notnull' => true, 'default' => '']],
					'description' => [Types::TEXT, ['notnull' => true, 'default' => '']],
					'dismissible' => [Types::SMALLINT, ['notnull' => true, 'default' => 1]],
					'ends_at' => [Types::BIGINT, ['notnull' => false]],
					'excluded_groups' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
					'excluded_match' => [Types::STRING, ['length' => 8, 'notnull' => true, 'default' => 'any']],
					'frequency' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'event']],
					'gateway_channels' => [Types::TEXT, ['notnull' => true, 'default' => '[]']],
					'group_match' => [Types::STRING, ['length' => 8, 'notnull' => true, 'default' => 'any']],
					'guest_visible' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'headline' => [Types::STRING, ['length' => 180, 'notnull' => true]],
					'manual_order' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
					'max_per_day' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
					'max_total' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
					'message' => [Types::TEXT, ['notnull' => true]],
					'min_interval_minutes' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
					'name' => [Types::STRING, ['length' => 160, 'notnull' => true]],
					'notify_activity' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'notify_email' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'notify_users' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'page_filters' => [Types::TEXT, ['notnull' => true, 'default' => '{}']],
					'page_route' => [Types::STRING, ['length' => 255, 'notnull' => true, 'default' => '']],
					'placement' => [Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'any']],
					'presentation' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'banner']],
					'priority' => [Types::INTEGER, ['notnull' => true, 'default' => 10]],
					'published_at' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'starts_at' => [Types::BIGINT, ['notnull' => false]],
					'status' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'draft']],
					'text_align' => [Types::STRING, ['length' => 8, 'notnull' => true, 'default' => 'left']],
					'text_color' => [Types::STRING, ['length' => 9, 'notnull' => false]],
					'theme' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'info']],
					'translated_content' => [Types::TEXT, ['notnull' => true, 'default' => '{}']],
					'trigger_key' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'updated_at' => [Types::BIGINT, ['notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['guest_visible', 'status'], 'cc_guest_status', false],
					[['status', 'trigger_key'], 'cc_status_trigger', false],
				],
			],
			'cc_events' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'context_app' => [Types::STRING, ['length' => 32, 'notnull' => true, 'default' => 'any']],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'dedupe_key' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'event_uuid' => [Types::STRING, ['length' => 36, 'notnull' => true]],
					'trigger_key' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['dedupe_key'], 'cc_event_dedupe', true],
					[['uid', 'created_at'], 'cc_event_uid_time', false],
					[['event_uuid'], 'cc_event_uuid', true],
				],
			],
			'cc_deliveries' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'claim_token' => [Types::STRING, ['length' => 48, 'notnull' => true, 'default' => '']],
					'claim_until' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'clicked_at' => [Types::BIGINT, ['notnull' => false]],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'dismissed_at' => [Types::BIGINT, ['notnull' => false]],
					'event_id' => [Types::BIGINT, ['notnull' => true]],
					'impression_at' => [Types::BIGINT, ['notnull' => false]],
					'state' => [Types::STRING, ['length' => 16, 'notnull' => true, 'default' => 'pending']],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['event_id', 'campaign_id'], 'cc_delivery_event_campaign', true],
					[['campaign_id', 'uid', 'impression_at'], 'cc_delivery_frequency', false],
					[['uid', 'state'], 'cc_delivery_uid_state', false],
				],
			],
			'cc_leases' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'delivery_id' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'expires_at' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'token' => [Types::STRING, ['length' => 48, 'notnull' => true, 'default' => '']],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['uid', 'campaign_id'], 'cc_lease_uid_campaign', true],
				],
			],
			'cc_board_comments' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'deleted_at' => [Types::BIGINT, ['notnull' => false]],
					'message' => [Types::TEXT, ['notnull' => true]],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'updated_at' => [Types::BIGINT, ['notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['campaign_id', 'id'], 'cc_board_comments_campaign', false],
					[['uid', 'created_at'], 'cc_board_comments_uid', false],
				],
			],
			'cc_board_pub' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'broadcast_done' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'event_sent' => [Types::SMALLINT, ['notnull' => true, 'default' => 0]],
					'event_sent_at' => [Types::BIGINT, ['notnull' => false]],
					'last_error' => [Types::STRING, ['length' => 250, 'notnull' => false]],
					'version' => [Types::BIGINT, ['notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['event_sent', 'created_at'], 'cc_board_pub_event', false],
					[['campaign_id', 'version'], 'cc_board_pub_unique', true],
				],
			],
			'cc_board_sent' => [
				'columns' => [
					'id' => [Types::BIGINT, ['notnull' => true, 'autoincrement' => true]],
					'attempts' => [Types::INTEGER, ['notnull' => true, 'default' => 0]],
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'channel' => [Types::STRING, ['length' => 32, 'notnull' => true]],
					'claim_until' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'created_at' => [Types::BIGINT, ['notnull' => true]],
					'event_id' => [Types::BIGINT, ['notnull' => true, 'default' => 0]],
					'last_error' => [Types::STRING, ['length' => 250, 'notnull' => false]],
					'sent_at' => [Types::BIGINT, ['notnull' => false]],
					'status' => [Types::STRING, ['length' => 12, 'notnull' => true, 'default' => 'pending']],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
					'version' => [Types::BIGINT, ['notnull' => true]],
				],
				'primary' => ['id'],
				'indexes' => [
					[['status', 'claim_until'], 'cc_board_sent_claim', false],
					[['event_id', 'status', 'claim_until'], 'cc_sent_event_retry', false],
					[['campaign_id', 'version', 'event_id', 'uid', 'channel'], 'cc_sent_occ_unique', true],
					[['uid', 'channel', 'status'], 'cc_sent_user_channel', false],
				],
			],
			'cc_inbox_reads' => [
				'columns' => [
					'campaign_id' => [Types::BIGINT, ['notnull' => true]],
					'read_at' => [Types::BIGINT, ['notnull' => true]],
					'uid' => [Types::STRING, ['length' => 64, 'notnull' => true]],
				],
				'primary' => ['uid', 'campaign_id'],
				'indexes' => [
				],
			],
		];

		foreach ($definitions as $name => $definition) {
			if ($schema->hasTable($name)) {
				continue;
			}

			$table = $schema->createTable($name);
			foreach ($definition['columns'] as $column => [$type, $columnOptions]) {
				$table->addColumn($column, $type, $columnOptions);
			}
			$table->setPrimaryKey($definition['primary']);
			foreach ($definition['indexes'] as [$columns, $indexName, $unique]) {
				if ($unique) {
					$table->addUniqueIndex($columns, $indexName);
				} else {
					$table->addIndex($columns, $indexName);
				}
			}
			$changed = true;
		}

		return $changed ? $schema : null;
	}
}
