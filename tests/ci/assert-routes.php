<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

// Verify routes from controller attributes against Nextcloud's registered router.
define('OC_CONSOLE', 1);
$_SERVER['SCRIPT_FILENAME'] = '/var/www/html/occ';
chdir('/var/www/html');
require '/var/www/html/lib/base.php';

use OCP\AppFramework\Http\Attribute\FrontpageRoute;

$router = \OC::$server->get(\OC\Route\Router::class);
$router->loadRoutes('communications');
$registered = $router->getRouteCollection()->all();
$expected = [];
foreach (glob(__DIR__ . '/../../lib/Controller/*Controller.php') ?: [] as $file) {
	$controller = basename($file, '.php');
	$class = 'OCA\\Communications\\Controller\\' . $controller;
	$reflection = new ReflectionClass($class);
	$controllerName = strtolower(substr($controller, 0, -strlen('Controller')));
	foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
		foreach ($method->getAttributes(FrontpageRoute::class) as $attribute) {
			$route = $attribute->newInstance();
			$name = 'communications.' . $controllerName . '.' . strtolower($method->getName());
			$path = '/apps/communications' . $route->getUrl();
			$verb = strtoupper($route->getVerb());
			if (isset($expected[$name]) && $expected[$name]['path'] !== $path) {
				throw new RuntimeException('Ambiguous route declarations for ' . $name);
			}
			$expected[$name] ??= ['path' => $path, 'methods' => []];
			$expected[$name]['methods'][] = $verb;
		}
	}
}
if ($expected === []) {
	throw new RuntimeException('No attributed controller routes were discovered');
}

$failures = [];
foreach ($expected as $name => $declaration) {
	$route = $registered[$name] ?? null;
	if ($route === null || $route->getPath() !== $declaration['path']
		|| array_diff($declaration['methods'], array_map('strtoupper', $route->getMethods())) !== []) {
		$failures[] = sprintf('Controller route not registered: %s %s', $name, $declaration['path']);
	}
}
foreach ($registered as $name => $route) {
	if (str_starts_with($name, 'communications.') && !isset($expected[$name])) {
		$failures[] = 'Unexpected route outside controller attributes: ' . $name;
	}
}

if ($failures !== []) {
	fwrite(STDERR, implode("\n", $failures) . "\n");
	exit(1);
}
printf("All %d annotated controller routes resolve in Nextcloud.\n", count($expected));
