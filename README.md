<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Communications

**Free and open-source communications for organizations using Nextcloud.** Deliver institutional notices and event-driven messages through your own infrastructure, with control over recipients, visibility, privacy, and delivery channels.

Communications helps teams publish an announcement once and choose how it reaches people: an Inbox in Nextcloud, contextual banners or modal dialogs, Nextcloud Notifications, Activity, email, and optional messaging providers exposed by Two-Factor Gateway. Schedule publications or connect them to trusted events from installed applications. Each channel has its own eligibility and consent rules.

## Free to use, with optional professional services

**The software is free.** You may study, install, operate, share, and modify the source under the [AGPL-3.0-or-later](LICENSE), without paying a license fee or purchasing support. The license obligations still apply to distribution and network use. You provide your own compatible Nextcloud infrastructure or arrange hosting separately.

**Professional help is optional, not a condition of use.** [LibreCode Coop](https://librecode.coop/) offers services for organizations that need help with:

- Installation, managed infrastructure, operations, updates, and maintenance.
- Support agreements for organizations, deployment guidance, and incident response.
- Custom functionality and user-interface adaptations for institutional workflows.
- Integration with other applications, messaging systems, and existing business processes.
- Training and technical consulting for an organization's own administrators and developers.

For a service proposal, contact **[contact@librecode.coop](mailto:contact@librecode.coop)**. Choosing a service provider does not change the open-source license or create a paid-only edition.

## Help sustain development

Communications is maintained as free software. Development, security review, documentation, translation, testing, and compatibility work all require continued effort. You can support that work by contributing code and tests, reviewing issues, improving documentation and translations, sponsoring development, or contracting professional services. **The project needs ongoing community and financial support to remain sustainable; paying is not required to use it.**

**Development status:** experimental and not yet released. Its version is defined in `appinfo/info.xml`. Testing targets Nextcloud 36 development snapshots; the app is not certified for production or App Store distribution. Do not treat experimental functionality as a production service-level guarantee.

## Identity and requirements

- App ID: `communications` (directory name must match).
- Display name: **Communications**; Portuguese localization: **Comunicações**.
- Nextcloud **36** development version is defined in `appinfo/info.xml`. Supported PHP versions are derived from the Nextcloud compatibility matrix in CI; Composer validates the package constraints.
- CI tests install a disposable Nextcloud 36 development instance with SQLite, PostgreSQL and MariaDB. Stable-version compatibility and production suitability require separate certification.
- Optional Nextcloud apps: Notifications, Activity, Dashboard, Flow and Two-Factor Gateway. Providers must be configured separately.

Communications is an unreleased application with one canonical initial schema. For development, reinstall into an isolated disposable database whenever the unpublished schema changes. Do not share a database with the separate `contextualcampaigns` proof-of-concept app because its `cc_` table names overlap.

## Basic use

1. Build the app from source (`npm ci && npm run build`) and install it in a disposable Nextcloud 36 instance under `custom_apps/communications`, or extract the CI-validated test archive under that app ID.
2. Enable it with `php occ app:enable communications` from Nextcloud's root as the web-server user.
3. Open **Communications → Publications** and choose **New publication** (localized as **Comunicações → Publicações → Nova publicação** in Brazilian Portuguese).
4. Choose *when* the publication is active (immediately, scheduled or an event), compose the message, choose eligible recipients, and select delivery channels. Banner and modal are mutually exclusive; other channels can be combined without requiring an Inbox copy.
5. Review, publish and verify delivery/visibility with test recipients. The recipient's Inbox is separate from a sender's management view.

Event triggers are discoverable from enabled applications; whether an event has a reliable *business-completion* meaning depends on the source application. An internal PHP event is not equivalent to an external webhook or proof of a document signature.

## Development

```sh
npm ci --include=dev --include=optional
npm run build
npm run check-types
npm run lint
npm run stylelint
npm run test:unit
npm run test:mutation
composer install # installs isolated PHPUnit, PHPStan, PHP-CS-Fixer and Infection toolchains in vendor-bin/
composer cs:check
composer test
composer analyse
composer mutation:test # requires PCOV or Xdebug coverage
```


### Frontend source and installable test archive

`src/` contains all handwritten JavaScript, TypeScript, Vue and CSS. The `js/` and `css/` directories are generated by `npm run build`, and **must not be committed**. The lightweight `src/loader.js` and `src/guest.js` scripts are copied unchanged to their Nextcloud-facing `js/` filenames; Vite produces the three heavier bundles. Every build first replaces stale generated assets.

For distribution testing, run `npm run test:package` and `npm run package:app`. The latter compiles all assets, validates the required Nextcloud entry points, and creates `build/artifacts/communications-<version>.tar.gz`. Its archive has one `communications/` root and excludes development directories such as `src/`, `tests/`, `.github/`, and `node_modules/`. The separate CI packaging workflow uploads an **unsigned, review-only** archive; it does not publish to the Nextcloud App Store, sign the app, or certify it for production.

Vue single-file components (`<template>`, `<script setup>` and `<style scoped>`) follow the official `@nextcloud/prettier-config`, using 4-column tabs; `npm run lint` checks their formatting and `npm run format:vue:fix` normalizes it. The Vue components and browser entrypoints use strict TypeScript. Campaign form serialization/validation is an independently tested pure module, and HTTP calls use the official `@nextcloud/axios` and `@nextcloud/router` packages. Vitest exercises front-end contracts; Stryker currently enforces an 85% mutation-score floor for the security-critical frontend CTA link sanitizer, not the entire frontend. Infection enforces a separate 90% mutation-score floor for the backend `CtaUrl` sanitizer, not the entire PHP application. Application styles use the Nextcloud Vue component library, theme variables and scoped Vue SFC styles. Global CSS is limited to 13 lines for the Nextcloud app mount; test assertions enforce this boundary. Avoid introducing CSS overrides for Nextcloud component internals. Migration history is intentionally a single initial schema for this unreleased app.

All shared workflows are synchronized from the [`LibreCodeCoop/.github` catalog](https://github.com/LibreCodeCoop/.github/tree/main/workflow-templates) and locked in `.github/actions-lock.txt`. The shared organization catalog tracks immutable upstream Nextcloud sources, including PHPStan, the three PHPUnit database workflows and Playwright, and normalizes public runner labels. Only `playwright.yml.patch` remains app-specific for its database fixtures, routing and LibreSign event scenario. The frontend/backend mutation workflows remain local. The shared catalog updates from [LibreCodeCoop/.github#115](https://github.com/LibreCodeCoop/.github/pull/115) are merged and pinned by the consumer lock. The Playwright overlay configures the SQLite/PostgreSQL/MariaDB shard matrix and invokes the local disposable test runner, which also verifies route/schema contracts and cleanup. Environment-tagged blob reports avoid collisions when GitHub merges artifacts. Never hand-edit a managed workflow; edit only its application-specific patch and verify upstream replay.

`.tx/config` and `.l10nignore` prepare native source extraction for the official Nextcloud Transifex resource. The Portuguese `l10n` catalogs are retained as working fallbacks until the project is onboarded and the Nextcloud translation bot can sync replacements; creating a resource configuration alone does not register it or authorize the bot.

The PHP tests and analysis require a PHP runtime compatible with the target Nextcloud server and Composer. For full browser tests use an **isolated** Nextcloud 36 test instance with installed relevant apps, fixture users and Chrome/Chromium, then run `npm run test:e2e`. The LibreSign compatibility test is opt-in through `npm run test:e2e:libresign`; consult [testing and integration](docs/TESTING.md) for its exact constraints. Browser tests do not contact production providers.

## Documentation

- [Architecture and event model](docs/ARCHITECTURE.md)
- [Delivery-channel contracts](docs/DELIVERY_CHANNELS.md)
- [Two-Factor Gateway and consent](docs/TWO_FACTOR_GATEWAY.md)
- [Local testing and verification](docs/TESTING.md)
- [API overview](docs/API.md)
- [Contribution guide](CONTRIBUTING.md)
- [Security reporting](SECURITY.md)

## Security and current limitations

Authentication, server-side audience authorization, CSRF protection, opt-in preferences, idempotent delivery and restricted URL schemes are part of the application contract. **Do not confuse their presence with a complete security audit.** Event principals are authenticated Nextcloud accounts; invited external signers are not automatically mapped to accounts. The app does not deliver advertisements via a third-party ad network, provide billing, or manage user-consent policy outside the documented preferences.

The application uses Nextcloud's internal router discovery API for some page-trigger options; that compatibility must be rechecked as Nextcloud evolves. Native message delivery can be delayed by cron. No end-to-end delivery guarantee is made for an unconfigured external gateway.

## License

Communications is free software under `AGPL-3.0-or-later` — see [LICENSE](LICENSE). The software may be used without paying license fees; professional support, hosting, integration, or customization are independent and optional services. Distributed dependencies retain their upstream copyright and license notices.
