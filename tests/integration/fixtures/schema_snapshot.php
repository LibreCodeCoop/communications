<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Run as a console script in the disposable Nextcloud test instance so that
// the schema assertion cannot be skipped by web request initialization.
define('OC_CONSOLE', 1);
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';
$db = \OC::$server->get(\OCP\IDBConnection::class);
$tables = [];
foreach ($db->createSchema()->getTables() as $table) {
	$name = $table->getName();
	if (!str_contains($name, 'cc_') || !preg_match('/(?:^|_)cc_[a-z_]+$/', $name)) {
		continue;
	}
	$key = substr($name, strpos($name, 'cc_'));
	$columns = [];
	foreach ($table->getColumns() as $column) {
		$columns[$column->getName()] = [
			'type' => strtolower((new \ReflectionClass($column->getType()))->getShortName()),
			'length' => $column->getLength(),
			'notnull' => $column->getNotnull(),
			'default' => $column->getDefault(),
			'autoincrement' => $column->getAutoincrement(),
		];
	}
	$indexes = [];
	foreach ($table->getIndexes() as $index) {
		$indexes[$index->getName()] = [
			'columns' => $index->getColumns(),
			'unique' => $index->isUnique(),
			'primary' => $index->isPrimary(),
		];
	}
	ksort($columns);
	ksort($indexes);
	$tables[$key] = ['columns' => $columns, 'indexes' => $indexes];
}
ksort($tables);

$expected = json_decode(
	(string)file_get_contents(__DIR__ . '/../schema-contract.sqlite.json'),
	true,
	512,
	JSON_THROW_ON_ERROR,
);

$failures = [];
if (array_keys($expected) !== array_keys($tables)) {
	$failures[] = 'Initial app table names differ from the reviewed schema';
}
$isSqlite = \OC::$server->get(\OCP\IConfig::class)->getSystemValueString('dbtype', '') === 'sqlite3';
foreach ($expected as $tableName => $contract) {
	if (!isset($tables[$tableName])) {
		$failures[] = "Missing table {$tableName}";
		continue;
	}
	$actual = $tables[$tableName];
	if (array_keys($actual['columns']) !== array_keys($contract['columns'])) {
		$failures[] = "Column names differ for {$tableName}";
	}
	foreach ($contract['columns'] as $name => $column) {
		if (!isset($actual['columns'][$name])) {
			continue;
		}
		$found = $actual['columns'][$name];
		// Databases normalize types, defaults and autoincrement differently.
		// Verify the full initial-install schema on SQLite, and the portable
		// nullability/length contract on PostgreSQL and MariaDB.
		if ($isSqlite && $found !== $column) {
			$failures[] = "Column differs: {$tableName}.{$name}";
		} elseif (!$isSqlite && (
			$found['notnull'] !== $column['notnull']
			|| ($column['length'] !== null && $found['length'] !== $column['length'])
		)) {
			$failures[] = "Column contract differs: {$tableName}.{$name}";
		}
	}
	if ($actual['indexes'] !== $contract['indexes']) {
		$failures[] = "Index contract differs for {$tableName}";
	}
}
if ($failures !== []) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}
echo sprintf("Initial schema contract passed: %d tables, %d columns.\n", count($tables),
	array_sum(array_map(static fn (array $table): int => count($table['columns']), $tables)));
