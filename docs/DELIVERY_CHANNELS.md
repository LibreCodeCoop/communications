<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# Independent delivery channels

Communications 0.29.0 provides one publication editor, a recipient audience, a lifecycle, and independently selected delivery channels. The trigger determines **when** a publication or recipient-scoped event becomes eligible; the channels determine **where** its content appears or is sent. A copy in the Inbox is optional.

## Choosing destinations

The editor supports a contextual **banner or modal**, the **Inbox**, **Nextcloud Notifications**, **Activity**, **email**, and optional **Two-Factor Gateway** messaging/SMS transports.

- Banner and modal are mutually exclusive visual presentations. Selecting one clears the other.
- All other channel choices may be combined. Enabling email or Notifications does not implicitly create an Inbox entry.
- At least one delivery destination is required; an empty selection produces HTTP 422.
- Channel readiness is checked separately. An installed app is not necessarily configured to deliver messages.
- Visual presentation options appear only for their selected format. Previews do not send actual messages or establish provider-specific rendering.

| Use case | Contextual presentation | Inbox | Other channels |
| --- | --- | --- | --- |
| Email only | None | No | Email |
| Nextcloud bell only | None | No | Notifications |
| WhatsApp only | None | No | Configured gateway and recipient consent |
| Inbox only | None | Yes | None |
| Banner plus email | Banner | Optional | Email |
| Modal plus Telegram | Modal | Optional | Configured Telegram gateway |
| Scheduled outbound message | None | Optional | One or more outbound channels |

`presentation=none` represents a publication without a banner or modal; `board_visible` separately controls an Inbox copy. The legacy `presentation=board` is still accepted for previously stored data, but is not the preferred new representation.

## When a publication is sent

**Immediate and scheduled publications:** After activation, `BoardPublicationService` prepares due publications and background jobs process recipients in bounded batches. Asynchronous channels are queued separately, and a banner or modal appears on a recipient's next eligible visit. Reopening a page does not automatically resend an outbound message. Reactivating a publication can initiate a new activation round.

**Individual events:** A trusted file, application, page, Flow or webhook occurrence is associated with the relevant recipient and eligible publication. Event-based delivery is **recipient-scoped**, including selected nonvisual channels; it is not an unrestricted broadcast on activation. Frequency, audience restrictions, preferences and per-recipient/channel idempotency still apply.

**Inbox visibility:** A publication sent solely by email, Notification, Activity or gateway is not listed as an Inbox item. Recipients can follow authorized detail links when an eligible delivery exists, but unrelated users cannot access a publication by guessing its ID.

**Anonymous visitors:** Public banners require an explicit safe configuration: an institutional, time-scheduled banner for all visitors with unrestricted placement. No private audience data or personalized outbound channel is exposed in the anonymous feed.

The app maintains an outbox keyed by publication, activation, recipient and channel (and by event where applicable). Retries are possible. A third-party provider may accept a message without returning a definitive response, so **exactly-once external delivery is not guaranteed**.

## Channel prerequisites

| Channel | Prerequisite |
| --- | --- |
| Inbox | Eligible logged-in recipient and `board_visible` enabled |
| Notifications | Nextcloud **Notifications** app enabled; `notify_push` alone is not a replacement |
| Activity | Nextcloud **Activity** app enabled |
| Email | Working Nextcloud SMTP configuration and a valid recipient address |
| Two-Factor Gateway | Enabled app, fully configured direct provider, valid recipient destination and explicit per-user consent |

Gateway provider settings, opt-in requirements, supported destination formats and sponsored-content consent are documented in [Two-Factor Gateway and consent](TWO_FACTOR_GATEWAY.md). Do not reuse the recipient's authentication/OTP identifiers as consent for editorial messages.

## Tests and known limits

The app's current browser acceptance tests are `tests/e2e/app-smoke.spec.mjs` (publication, persistence and audience isolation on three database engines) and `tests/e2e/libresign-event.spec.mjs` (generic native-event delivery using a synthetic LibreSign fixture on SQLite only). Route and schema contracts are also checked during the Playwright CI jobs. These tests do **not** send live email, SMS or gateway messages, nor do they prove receipt from an external provider.

Test the actual outbound provider separately in an authorized, isolated environment before treating it as production-ready. See [Testing and evidence](TESTING.md) and [API](API.md).
