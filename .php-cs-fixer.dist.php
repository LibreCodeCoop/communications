<?php
/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Nextcloud\CodingStandard\Config;

$config = new Config();
$config->getFinder()
    ->ignoreVCSIgnored(true)
    ->in(__DIR__ . '/lib')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/templates');

return $config;
