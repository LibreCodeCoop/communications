<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 * Static-analysis-only declarations for the Nextcloud private runtime APIs
 * referenced in exactly two isolated compatibility components. Never load
 * this file inside a Nextcloud request.
 */

namespace {
	class OC {
		public static string $SERVERROOT;
		public static string $WEBROOT;
	}
}

namespace OC\Route {
	class Router {
		public function loadRoutes(?string $app = null, bool $skipLoadingCore = false): void {
		}
		public function getRouteCollection(): \Symfony\Component\Routing\RouteCollection {
			return new \Symfony\Component\Routing\RouteCollection();
		}
	}
}

namespace OCA\WorkflowEngine\Entity {
	class File {
	}
}
