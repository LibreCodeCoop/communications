<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Testing and evidence

`composer test` executes isolated PHPUnit cases for safe URLs and event-subscription guardrails. `composer analyse` checks PHP sources using Nextcloud's public OCP API plus development-only stubs for internal classes not distributed by `nextcloud/ocp`. `npm run test:unit` tests safe client-side links. `npm run build` builds all frontend bundles. `npm run test:e2e:libresign` uses Playwright in a disposable Nextcloud installation where LibreSign has been enabled.

The external-app test requires:

- `NC_BASE_URL` reachable only from the test environment, default `http://127.0.0.1:18884` locally.
- `NC_CONTAINER`: isolated test container, default `communications-ci-nextcloud`.
- `NC_TEST_PASSWORD`: password shared by test accounts only; never production credentials.
- `NC_ADMIN_USER` (default `qa_admin`), `NC_RECIPIENT_USER` (default `poc_second`) and `NC_UNRELATED_USER` (default `qa_unrelated`).
- `PLAYWRIGHT_CHROMIUM_EXECUTABLE` for an existing local Chromium; omit when Playwright manages browsers.

The test discovers the LibreSign aggregate-completion event in the standard UI, creates a publication through the same authenticated API as other apps, verifies it is **not** delivered before a trusted event, then runs the fixture to invoke the generic listener, processes the outbox and verifies that only the eligible recipient can read it. This requires the matching LibreSign event type in the enabled checkout, but does not send messages through real external providers or sign a real PDF. The test archives and purges the created publication in `finally`.

Acceptance evidence must include the tested Nextcloud and LibreSign revisions, database, exact commands and exit codes. A test that passed once in a development snapshot does not certify the eventual stable Nextcloud 36 release. PostgreSQL and MariaDB must be installed and tested separately; do not infer compatibility from SQLite migrations alone.

## CI source integrity

The Nextcloud archive source is the development channel (`latest-master` for the current manifest requirement). The bootstrap reads the supported version from `appinfo/info.xml` and refuses any other version. When upstream master advances beyond the compatible version in the manifest, replace the archive source with a verified supported release artifact; the test will fail closed rather than silently testing the wrong version. The LibreSign test pins a specific reviewed upstream commit and compiles its frontend assets; it is never used as an app-specific production adapter.

### Scoped event delivery in CI

The browser compatibility scenario creates a synthetic LibreSign completion event and executes only its queued `EventChannelDeliveryJob` using the **test-only** CLI fixture in `tests/integration/fixtures/execute_queued_event_delivery.php`. This exercises the production queue worker without running Nextcloud's global `cron.php`, which may execute unrelated jobs for installed apps and exceed browser-test timeouts. The fixture refuses events outside its synthetic LibreSign recipient and requires exactly one matching queued job. The browser still verifies authorized delivery and denial to unrelated users.

### Quality gates

Development uses the organization-managed lint, type-check, build, Node test and REUSE workflows. Run `npm run lint`, `npm run check-types`, `npm run stylelint`, `npm run test:unit`, `composer cs:check`, `composer test`, and `composer analyse` before review. The optional `npm run test:mutation` tests the frontend URL allowlist using Stryker's Vitest runner and blocks scores below 85%. The separate `composer mutation:test` uses Infection with PCOV/Xdebug and blocks backend URL-sanitizer scores below 90%. Both are intentionally scoped to security-sensitive URL validation; neither claims mutation coverage for unrelated frontend modules or the full PHP application. The separate Playwright workflow validates recipient isolation, route/schema contracts, and the generic LibreSign event contract on disposable Nextcloud instances.

The consolidated schema is compared with the pre-consolidation contract in `tests/integration/schema-contract.sqlite.json`. The assertion checks full SQLite column types/defaults and portable nullability/length/index contracts in PostgreSQL and MariaDB. No migration of the old laboratory app ID is implied.

### Official Nextcloud CI templates

The GitHub Actions flows for PHPStan (`phpstan.yml`), PHPUnit on SQLite/MariaDB/PostgreSQL (`phpunit-*.yml`) and Playwright (`playwright.yml`) are copied from `nextcloud/.github` by the existing LibreCode workflow synchronization action. They are not independently maintained forks. App-specific differences live in adjacent `.yml.patch` files: our Composer aliases and pinned OCP dependency, generic LibreSign fixture, database matrix, route/schema checks and container teardown. `.github/nextcloud-actions-lock.txt` records the official catalog revision and hashes; `.github/actions-lock.txt` remains exclusive to the LibreCode catalog. The reusable Nextcloud Playwright report-merging workflow uses `tests/playwright/merge.config.ts`.

The CSS framework is the native `@nextcloud/vue` component and theme-variable system, not an additional Bootstrap-style framework. Rules needed for banners, administration, Inbox and automation are kept in `scoped` Vue SFC styles. The residual global stylesheet contains only the Nextcloud mount geometry. `@nextcloud/axios` handles the ordinary authenticated HTTP requests; only page-unload acknowledgements use native `fetch(keepalive)` because the standard Axios transport does not provide an equivalent browser guarantee.

### Nextcloud translation and icon conventions

`img/app.svg` uses white monochrome fill for the main Nextcloud navigation; `img/app-dark.svg` uses the identical geometry with `currentColor` for light backgrounds. Accent color comes from the instance theme, not a brand-specific SVG fill.

`.tx/config` and `.l10nignore` prepare the app for Nextcloud's official Transifex extraction process. The existing `pt_BR.js`/`pt_BR.json` are preserved as translation fallbacks during development. **These files do not activate Transifex themselves.** Nextcloud community onboarding requires approval of the resource and `@nextcloud-bot` write access; after onboarding, the bot should own the generated catalogs and they should no longer be edited by hand. Do not drop the Portuguese catalogs before the bot is actually producing replacements, or installed releases will lose their translations.
