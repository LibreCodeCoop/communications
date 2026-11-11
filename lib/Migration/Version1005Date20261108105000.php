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
 * Optional server-declared web route pattern for page-visit campaigns.
 * It stores no actual user URL, IDs, query strings or document paths.
 */
class Version1005Date20261108105000 extends SimpleMigrationStep {
 public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
  $schema = $schemaClosure();
  if (!$schema->hasTable('cc_campaigns')) { return null; }
  $table = $schema->getTable('cc_campaigns');
  if ($table->hasColumn('page_route')) { return null; }
  $table->addColumn('page_route', Types::STRING, ['length'=>255, 'notnull'=>true, 'default'=>'*']);
  return $schema;
 }
}
