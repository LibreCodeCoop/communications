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
