<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Contributing

This is a Nextcloud app under active development. Reproduce bugs on an isolated instance, document expected and observed behavior, and include a failing regression test when possible. Run `composer test`, `composer analyse`, `npm run test:unit`, `npm run build`, and applicable Nextcloud integration tests before requesting review. Do not use production credentials or real user messages as fixtures.

Use Conventional Commits (`feat:`, `fix:`, `test:`, `docs:` etc.), sign off every commit (`Signed-off-by:` with the actual author identity) in accordance with DCO and use verifiable cryptographic signatures. A passing local test does not replace independent review, required CI, or permission to release. Never commit `.env`, backups, user content, credentials, database dumps or private laboratory settings.

Source changes must not add a hard-coded integration for one third-party Nextcloud app when the generic event contract suffices. Dedicated external-app compatibility tests belong under `tests/e2e` or integration tests; they should run against the same production code path used by other apps.
