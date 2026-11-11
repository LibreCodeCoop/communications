<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Communications

**An experimental Nextcloud app for institutional publications, notifications, and event-driven communication.**

Administrators publish information to eligible audiences using a single editor and independently selectable delivery channels: contextual banner **or** modal, Inbox, Nextcloud Notifications, Activity, email, and optional transports from Two-Factor Gateway. Events from installed apps are discovered through Nextcloud contracts; **no LibreSign-specific runtime adapter is included**.

**Status:** development / proof of concept, version `0.29.0`. Tested on Nextcloud 36 development snapshots. Nextcloud 36 is not yet a stable release as of November 2026. This app has **not** been certified for production, app-store distribution, or advertising/billing. Do not point experimental acceptance scripts at production installations.

## Identity and requirements

- App ID: `communications` (directory name must match).
- Display name: **Communications**; Portuguese localization: **Comunicações**.
- PHP >= 8.3 and Nextcloud **36** development version (in `appinfo/info.xml`).
- Local integration tests have passed on SQLite, PostgreSQL and MariaDB in Nextcloud 36-dev. Remote CI validation and stable-version certification remain required.
- Optional Nextcloud apps: Notifications, Activity, Dashboard, Flow and Two-Factor Gateway. Providers must be configured separately.

This project was derived from the local `contextualcampaigns` proof of concept. **It is a new app ID**, not a drop-in upgrade. Do not install it alongside `contextualcampaigns` in the same database; both historically use `cc_` tables. Data migration and upgrade from the old laboratory identifier are not supported.

## Basic use

1. Install the app in a Nextcloud 36 test instance under `custom_apps/communications`.
2. Enable it with `php occ app:enable communications` from Nextcloud's root as the web-server user.
3. Open **Comunicações → Publicações** and choose **Nova publicação**.
4. Choose *when* the publication is active (immediately, scheduled or an event), compose the message, choose eligible recipients, and select delivery channels. Banner and modal are mutually exclusive; other channels can be combined without requiring an Inbox copy.
5. Review, publish and verify delivery/visibility with test recipients. The recipient's Inbox is separate from a sender's management view.

Event triggers are discoverable from enabled applications; whether an event has a reliable *business-completion* meaning depends on the source application. An internal PHP event is not equivalent to an external webhook or proof of a document signature.

## Development

```sh
npm ci --include=dev --include=optional
npm run build
npm run test:unit
composer install
composer test
composer analyse
```

The PHP tests and analysis require PHP 8.3+ and Composer. For full browser tests use an **isolated** Nextcloud 36 test instance with installed relevant apps, fixture users and Chrome/Chromium, then run `npm run test:e2e`. The LibreSign compatibility test is opt-in through `npm run test:e2e:libresign`; consult [testing and integration](docs/TESTING.md) for its exact constraints. Browser tests do not contact production providers.

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

`AGPL-3.0-or-later` — see [LICENSE](LICENSE). Distributed dependencies retain their upstream copyright and license notices.
