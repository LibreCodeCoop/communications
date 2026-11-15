<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

use OC\Route\Router;
use OCP\App\IAppManager;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\TemplateResponse;

/**
 * Discover UI routes from Nextcloud's already-defined router, including PHP #[FrontpageRoute]
 * attributes. OCS / API / AJAX / response endpoints are not browser pages and must never be
 * offered as campaign triggers. The router collection API is currently internal to Nextcloud.
 * Keep that dependency isolated here; it can be swapped if upstream exposes an API.
 */
final class WebRouteCatalog {
	public function __construct(
		private Router $router,
		private IAppManager $apps,
		private \OCP\IL10N $l10n,
	) {
	}

	/**
	 * Enumerate route *patterns* only, never actual user URLs, query strings,
	 * document paths, share tokens or IDs.
	 *
	 * @return list<array{value:string,label:string,description:string}>
	 */
	public function routesFor(string $app): array {
		if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app)
			|| $app === 'communications'
			|| !$this->apps->isEnabledForAnyone($app)) {
			return [];
		}

		// Route files and PHP attributes must both be included. An enabled app
		// might not yet have been loaded in this request.
		$this->apps->loadApp($app);
		$this->router->loadRoutes($app);
		// TRANSLATORS Label for the entry point (/) of an application's pages.
		$mainPageLabel = $this->l10n->t('Main page');
		// TRANSLATORS Prefix before a route path, e.g. 'Page /settings/user'.
		$pagePrefix = $this->l10n->t('Page ');
		$routes = [];
		foreach ($this->router->getRouteCollection()->all() as $name => $route) {
			if (!str_starts_with($route->getPath(), '/apps/' . $app . '/')) {
				continue;
			}
			$methods = $route->getMethods();
			if ($methods !== [] && !in_array('GET', $methods, true)) {
				continue;
			}
			$pattern = $route->getPath();
			if (!self::isWebRoute($pattern, $app, (string)$name)
				|| !$this->isAuthenticatedHtmlRoute($app, $route)) {
				continue;
			}
			$tail = substr($pattern, strlen('/apps/' . $app));
			$description = $pattern;
			$routes[$pattern] = [
				'value' => $pattern,
				'label' => $tail === '/' ? $mainPageLabel : $pagePrefix . $tail,
				'description' => $description,
			];
			if (count($routes) >= 100) {
				break;
			}
		}
		// Do not invent a root route for apps with no declared page. Many apps
		// expose only OCS/API endpoints; synthetic routes falsely mark them as UI.
		uasort($routes, static function (array $a, array $b): int {
			$aVars = substr_count($a['value'], '{');
			$bVars = substr_count($b['value'], '{');
			return ($aVars <=> $bVars) ?: (strlen($a['value']) <=> strlen($b['value'])) ?: strcmp($a['value'], $b['value']);
		});
		return array_values($routes);
	}

	/**
	 * This POC injects only authenticated Nextcloud pages. A GET route can
	 * also return binary data or render a public guest page without our loader.
	 * Inspect controller metadata when available, rather than treating every
	 * GET endpoint as a browser navigation target.
	 */
	private function isAuthenticatedHtmlRoute(string $app, object $route): bool {
		$caller = $route->getDefault('caller');
		if (!is_array($caller) || count($caller) < 3 || !is_string($caller[1]) || !is_string($caller[2])) {
			return true; // Old route definitions: still subject to path exclusions.
		}
		try {
			$controller = $this->apps->getAppNamespace($app) . '\\Controller\\' . $caller[1];
			$method = new \ReflectionMethod($controller, $caller[2]);
			if ($method->getAttributes(PublicPage::class) !== []) {
				return false;
			}
			$return = $method->getReturnType();
			$types = $return instanceof \ReflectionUnionType ? $return->getTypes()
				: ($return instanceof \ReflectionNamedType ? [$return] : []);
			// A declared TemplateResponse (possibly with RedirectResponse) is a UI.
			foreach ($types as $type) {
				if (is_a($type->getName(), TemplateResponse::class, true)) {
					return true;
				}
			}
			foreach ($types as $type) {
				// Explicit non-HTML responses are not navigation, even when GET.
				if (in_array($type->getName(), [
					'OCP\\AppFramework\\Http\\JSONResponse',
					'OCP\\AppFramework\\Http\\DataResponse',
					'OCP\\AppFramework\\Http\\DataDownloadResponse',
					'OCP\\AppFramework\\Http\\FileDisplayResponse',
					'OCP\\AppFramework\\Http\\StreamResponse',
				], true)) {
					return false;
				}
			}
		} catch (\ReflectionException|\Error $error) {
			// Some custom app controllers cannot be reflected; rely on other guards.
		}
		return true;
	}

	public function isSelectable(string $app, string $pattern): bool {
		foreach ($this->routesFor($app) as $route) {
			if ($route['value'] === $pattern) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Server-side normalized request path. Never stores or returns real URLs.
	 * The POC excludes settings, public shares, arbitrary domains and APIs.
	 */
	public function safePagePath(string $app, string $path): string {
		if (!preg_match('/^[a-z0-9_-]{2,32}$/', $app) || strlen($path) > 360 || strlen($path) < 4
			|| preg_match('~[?#\x00-\x1f\x7f\\\\]~', $path)
			|| str_contains($path, '..') || str_contains($path, '//')) {
			return '';
		}
		if (str_contains($path, '/index.php/apps/')) {
			$path = substr($path, strpos($path, '/index.php/apps/') + strlen('/index.php'));
		}
		$needle = '/apps/' . $app . '/';
		if (!str_starts_with($path, $needle) && $path !== rtrim($needle, '/')) {
			return '';
		}
		if (!self::isWebRoute($path, $app, 'browser')) {
			return '';
		}
		return $path;
	}

	/**
	 * Per-variable rules are deliberately structured, not arbitrary PCRE.
	 * This avoids catastrophic backtracking and keeps UI validation predictable.
	 *
	 * @param array<string, array{operator:string,value?:string}> $rules
	 * @return array<string, array{operator:string,value:string}>
	 */
	public function normalizeFilters(string $pattern, array $rules): array {
		if ($pattern === '') {
			if ($rules !== []) {
				throw new \InvalidArgumentException($this->l10n->t('Select a route before filtering variables.'));
			}
			return [];
		}
		preg_match_all('/\{([A-Za-z][A-Za-z0-9_]*)\}/', $pattern, $matches);
		$variables = array_flip($matches[1]);
		if (count($rules) > 8) {
			throw new \InvalidArgumentException($this->l10n->t('Maximum of eight route filters.'));
		}
		$normalized = [];
		foreach ($rules as $name => $rule) {
			if (!is_string($name) || !isset($variables[$name]) || !is_array($rule)) {
				throw new \InvalidArgumentException($this->l10n->t('Unknown route filter.'));
			}
			$operator = $rule['operator'] ?? null;
			$value = $rule['value'] ?? '';
			if (!is_string($value) || mb_strlen($value) > 96) {
				throw new \InvalidArgumentException($this->l10n->t('Invalid route filter value.'));
			}
			if ($operator === 'digits') {
				if ($value !== '') {
					throw new \InvalidArgumentException($this->l10n->t('Numeric route filters do not accept a value.'));
				}
			} elseif (in_array($operator, ['equals', 'prefix'], true)) {
				// Match a single safe URI segment, never a slash, query, fragment or PCRE.
				if ($value === '' || !preg_match('/^[A-Za-z0-9._~-]{1,96}$/D', $value) || str_contains($value, '..')) {
					throw new \InvalidArgumentException($this->l10n->t('Enter a valid value without slashes or regular expressions.'));
				}
			} else {
				throw new \InvalidArgumentException($this->l10n->t('Invalid route filter operator.'));
			}
			$normalized[$name] = ['operator' => $operator,'value' => $value];
		}
		return $normalized;
	}

	/** Match a declared route and optional safe constraints on its variables. */
	public function matches(string $pattern, string $app, string $browserPath, array $filters = []): bool {
		$path = $this->safePagePath($app, $browserPath);
		if ($path === '') {
			return false;
		}
		if ($pattern === '') {
			return $filters === [];
		}
		if (!self::isWebRoute($pattern, $app, 'matcher')) {
			return false;
		}
		$escaped = preg_quote(rtrim($pattern, '/'), '~');
		preg_match_all('/\{([A-Za-z][A-Za-z0-9_]*)\}/', $pattern, $variables);
		foreach ($variables[1] as $variable) {
			$escaped = str_replace('\{' . $variable . '\}', '(?P<' . $variable . '>[^/]+)', $escaped);
		}
		if (preg_match('~^' . $escaped . '/?$~D', $path, $matched) !== 1) {
			return false;
		}
		foreach ($filters as $name => $rule) {
			if (!isset($matched[$name]) || !is_array($rule)) {
				return false;
			}
			$segment = $matched[$name];
			$operator = $rule['operator'] ?? '';
			$value = $rule['value'] ?? '';
			if ($operator === 'equals' && $segment !== $value) {
				return false;
			}
			if ($operator === 'prefix' && !str_starts_with($segment, $value)) {
				return false;
			}
			if ($operator === 'digits' && !ctype_digit($segment)) {
				return false;
			}
			if (!in_array($operator, ['equals','prefix','digits'], true)) {
				return false;
			}
		}
		return true;
	}

	public static function isWebRoute(string $pattern, string $app, string $routeName): bool {
		if (strlen($pattern) > 255 || !str_starts_with($pattern, '/apps/' . $app . '/')) {
			return false;
		}
		if (preg_match('~\.(?:api|ajax|ocs|download|thumbnail|preview|serviceworker)\.~i', $routeName)) {
			return false;
		}
		if (preg_match('~/(?:api|ocs|ajax|directediting|download|preview|thumbnail|thumb|service-worker|auth|token|health|ws|rss|feed|pdf|crl)(?:/|$)~i', $pattern)) {
			return false;
		}
		if (preg_match('~/(?:[^/]*\.(?:json|xml|js|css|png|jpe?g|webp|svg|pdf|ics|zip|crl|pem|der|p12|pfx)|[^/]*service-worker[^/]*)$~i', $pattern)) {
			return false;
		}
		if (preg_match('~\{(?:token|secret|hash|password|signature|csrf|code|path)\}~i', $pattern)) {
			return false;
		}
		return true;
	}
}
