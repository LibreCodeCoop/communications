<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/** Initial installation schema for the unreleased Communications application. */
final class Version1000Date20261107190000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		$schema = $schemaClosure();
		$changed = false;

		$definitions = InitialSchema::definitions();

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
