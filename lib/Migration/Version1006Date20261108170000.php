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

/** Structured conditions for named segments of server-declared UI routes. */
class Version1006Date20261108170000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table = $schema->getTable('cc_campaigns');
  if ($table->hasColumn('page_filters')) { return null; }
  $table->addColumn('page_filters', Types::TEXT, ['notnull'=>true,'default'=>'{}']);
  return $schema;
 }
}
