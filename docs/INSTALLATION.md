<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Installation and first steps (development preview)

Communications is unreleased. These steps are for isolated, disposable Nextcloud 36 development installations; they are **not** production deployment instructions.

## Compatibility and identity

- App ID: `communications` (directory name must match).
- Display name: **Communications**; Portuguese localization: **Comunicações**.
- Nextcloud **36** development version is defined in `appinfo/info.xml`. Supported PHP versions are derived from the Nextcloud compatibility matrix in CI; Composer validates the package constraints.
- CI tests install a disposable Nextcloud 36 development instance with SQLite, PostgreSQL and MariaDB. Stable-version compatibility and production suitability require separate certification.
- Optional Nextcloud apps: Notifications, Activity, Dashboard, Flow and Two-Factor Gateway. Providers must be configured separately.

Communications is an unreleased application with one canonical initial schema. For development, reinstall into an isolated disposable database whenever the unpublished schema changes. Do not share a database with the separate `contextualcampaigns` proof-of-concept app because its `cc_` table names overlap.

## Try the app

1. Build the app from source (`npm ci && npm run build`) and install it in a disposable Nextcloud 36 instance under `custom_apps/communications`, or extract the CI-validated test archive under that app ID.
2. Enable it with `php occ app:enable communications` from Nextcloud's root as the web-server user.
3. Open **Communications → Publications** and choose **New publication** (localized as **Comunicações → Publicações → Nova publicação** in Brazilian Portuguese).
4. Choose *when* the publication is active (immediately, scheduled or an event), compose the message, choose eligible recipients, and select delivery channels. Banner and modal are mutually exclusive; other channels can be combined without requiring an Inbox copy.
5. Review, publish and verify delivery/visibility with test recipients. The recipient's Inbox is separate from a sender's management view.

Event triggers are discoverable from enabled applications; whether an event has a reliable *business-completion* meaning depends on the source application. An internal PHP event is not equivalent to an external webhook or proof of a document signature.


For administration and event integration details, see [Architecture](ARCHITECTURE.md), [Delivery channels](DELIVERY_CHANNELS.md), and [API](API.md).
