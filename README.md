<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->

# Communications

**Reach the right people at the right time, directly in Nextcloud.**

Communications is a **free and open-source application for Nextcloud** that helps organizations reach their people without scattering important announcements across unrelated tools. Compose a publication once, choose who should see it and when, then deliver it through the channels that fit the message.

## Communicate where people already work

- **Contextual banners and dialogs:** show an announcement within Nextcloud, including relevant pages and applications, rather than waiting for people to check another inbox.
- **A dedicated Inbox:** keep institutional publications accessible after the first notification, with reading and commenting features where enabled.
- **Nextcloud-native channels:** use Notifications, Activity, and email independently or together, according to your configuration.
- **Optional messaging channels:** connect supported providers through the separate Two-Factor Gateway app when installed, configured, and allowed by recipient preferences.
- **Scheduled or event-driven publications:** publish immediately, within a defined period, or in response to registered Nextcloud application events.
- **Audience and visibility controls:** target users and groups, exclude selected audiences, and define which applications or routes can display a contextual message.
- **A single publication workspace:** write rich text, preview presentation, manage drafts and active notices, reorder publications, and follow estimated engagement metrics.

## From everyday notices to automated communication

Share an operational update, a service interruption, a policy announcement, or a message relevant to a particular application workflow. Administrators and authorized editors can manage publications centrally; recipients see the messages through the selected destinations.

Communications uses Nextcloud's own account and application context. Event-driven delivery depends on the semantics of the event supplied by the originating app; an event by itself is not proof that an external business process was completed.

## Free software, under your control

**No license fee. No mandatory subscription. No paid-only edition.** Communications is distributed as free software under the [GNU AGPL v3 or later](LICENSE). You can inspect, use, share, and adapt the source, subject to the license conditions. Host it on your own compatible Nextcloud infrastructure and keep control of where institutional communications are handled.

If you need help with hosting, deployment, integrations, accessibility, maintenance, or training, [LibreCode Coop](https://librecode.coop/) offers **optional** professional services. You can also use the software without contracting LibreCode or any other vendor.

## Explore and contribute

- [Delivery channels and recipient preferences](docs/DELIVERY_CHANNELS.md)
- [Architecture and event integration](docs/ARCHITECTURE.md)
- [Development and testing](docs/DEVELOPMENT.md)
- [API and integration reference](docs/API.md)
- [Contribute to the project](CONTRIBUTING.md)

The project grows through code, translations, testing, design, documentation, and financial support. You can help sustain development through [GitHub Sponsors](https://github.com/sponsors/libresign) or contact [LibreCode Coop](mailto:contact@librecode.coop) for professional services.
