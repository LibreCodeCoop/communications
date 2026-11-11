<?php
/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
declare(strict_types=1);

// Run only inside the disposable Nextcloud test container, never production.
// Console bootstrap avoids request dispatch and redirects of an unauthenticated browser.
define('OC_CONSOLE', 1);
// Nextcloud resolves bootstrap paths from SCRIPT_FILENAME, not just cwd.
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';

$expected = json_decode((string)file_get_contents(__DIR__ . '/routes-expected.json'), true, 512, JSON_THROW_ON_ERROR);
$router = \OC::$server->get(\OC\Route\Router::class);
$router->loadRoutes('communications');
$routes = $router->getRouteCollection()->all();
$prefix = '/apps/communications';
$matching = [];

foreach ($routes as $name => $route) {
    if (!str_starts_with($name, 'communications.')) {
        continue;
    }
    $matching[$name] = [
        'path' => $route->getPath(),
        'methods' => array_map('strtoupper', $route->getMethods()),
    ];
}

$failures = [];
foreach ($expected as $item) {
    $path = $prefix . ($item['url'] === '/' ? '/' : $item['url']);
    // Attribute routing canonicalizes controller and method names to lowercase
    // and removes underscores from compound controller names.
    $expectedName = 'communications.' . strtolower(str_replace('_', '', str_replace('#', '.', $item['name'])));
    $actual = $matching[$expectedName] ?? null;
    if ($actual === null || $actual['path'] !== $path || !in_array($item['verb'], $actual['methods'], true)) {
        $failures[] = sprintf('%s %s %s: expected named route missing or mismatched',
            $expectedName, $item['verb'], $path);
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    fwrite(STDERR, json_encode($matching, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(1);
}
printf("All %d declared controller routes resolve in Nextcloud.\n", count($expected));
