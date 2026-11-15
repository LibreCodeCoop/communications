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

PHPStan (`phpstan.yml`), PHPUnit on SQLite/MariaDB/PostgreSQL (`phpunit-*.yml`) and Playwright (`playwright.yml`) now come through **one** source, `LibreCodeCoop/.github`. That source already pins and renders the original Nextcloud templates, substituting public runner labels centrally. `.github/actions-lock.txt` is the only workflow lockfile in this application. The standard Composer `phpstan` and `test:unit` aliases are provided locally, with `phpstan.neon` declaring the target Nextcloud PHP minimum. There is only one application-specific overlay: `.github/workflows/playwright.yml.patch` (database matrix, generic LibreSign event fixture, schema/routes assertions and cleanup). The existing shared synchronizer applies this patch and preserves the previous valid workflow if application fails. The Playwright report-merging config remains `tests/playwright/merge.config.ts`. The shared catalog was published by merged [LibreCodeCoop/.github PR #115](https://github.com/LibreCodeCoop/.github/pull/115). The consumer lock records the published catalog SHA, and the application PR remains subject to its own CI and independent code review.

The CSS framework is the native `@nextcloud/vue` component and theme-variable system, not an additional Bootstrap-style framework. Rules needed for banners, administration, Inbox and automation are kept in `scoped` Vue SFC styles. The residual global stylesheet contains only the Nextcloud mount geometry. `@nextcloud/axios` handles the ordinary authenticated HTTP requests; only page-unload acknowledgements use native `fetch(keepalive)` because the standard Axios transport does not provide an equivalent browser guarantee.

### Playwright database matrix, shards and merged reports

The application-specific overlay is kept intentionally small. The shared Playwright workflow invokes `scripts/ci/run-playwright.sh`, which sources the disposable environment bootstrap, checks the route/schema contracts, runs the assigned shard, and always tears down the test-owned Docker Compose project. The script preserves the original failing exit status when cleaning up.

The CI matrix contains **four** jobs: SQLite shard **1/2**, SQLite shard **2/2**, PostgreSQL shard **1/1**, and MariaDB shard **1/1**. Currently, SQLite runs both E2E files while the other databases exclude the LibreSign fixture and run only the common application smoke test. Do not create shards with no selected tests; revise the matrix when the suite grows.

`playwright.config.mjs` emits a `blob` report on CI and assigns a `@db-<database>` tag. Playwright incorporates that tag and the shard number into each internal ZIP filename. GitHub artifact names include the database and shard too; **distinct artifact names alone do not prevent an internal ZIP collision** when `download-artifact` uses `merge-multiple: true`. The shared `merge-reports` job must retain all results, not merely pass. With the current test files, expect four test results across four distinct blob reports (two SQLite, one PostgreSQL and one MariaDB). Inspect the merged HTML report and log rather than assuming a green job proves completeness.

### Standard Vue indentation

The component `<template>`, `<script setup>` and `<style scoped>` blocks are formatted with Nextcloud's `@nextcloud/prettier-config` (four-column tabs). `npm run format:vue` checks for drift; `npm run format:vue:fix` applies the official formatter to every Vue SFC. The ordinary `npm run lint` includes the formatting check, so indentation drift fails the existing organization-managed ESLint workflow.

### Nextcloud translation and icon conventions

`img/app.svg` uses white monochrome fill for the main Nextcloud navigation; `img/app-dark.svg` uses the identical geometry with `currentColor` for light backgrounds. Accent color comes from the instance theme, not a brand-specific SVG fill.

`.tx/config` and `.l10nignore` prepare the app for Nextcloud's official Transifex extraction process. The existing `pt_BR.js`/`pt_BR.json` are preserved as translation fallbacks during development. **These files do not activate Transifex themselves.** Nextcloud community onboarding requires approval of the resource and `@nextcloud-bot` write access; after onboarding, the bot should own the generated catalogs and they should no longer be edited by hand. Do not drop the Portuguese catalogs before the bot is actually producing replacements, or installed releases will lose their translations.

### Installable package verification

The source tree deliberately does not track `js/` or `css/`. Both directories must be created by `npm run build` before an app can run in Nextcloud. The lightweight public guest and authenticated loader adapters are maintained in `src/` and copied at build time; their public asset names remain unchanged.

Run `npm run test:package` to check both the generated asset installer and the packaging contract. The tests cover successful asset installation, stale-output removal, missing/empty entry points, archive structure, and exclusion of development source. `npm run package:app` builds the frontend and creates an unsigned `build/artifacts/communications-<version>.tar.gz`, verifying that the archive actually includes the five JavaScript and four CSS runtime files. The independent `Verify installable application package` GitHub workflow runs both checks and makes the test archive available as a CI artifact for inspection. This is not an App Store publication or production certification.
