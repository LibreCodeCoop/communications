<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors -->
<!-- SPDX-License-Identifier: AGPL-3.0-or-later -->
# Architecture and event contract

**Object:** a publication with content, lifecycle, eligible audience and independent delivery-channel selections. The **event** decides *when* the publication becomes eligible; the **channels** decide *where* it is presented or sent. A persistent Inbox item is optional even when email or notifications are enabled.

**Core responsibilities:** `CampaignRepository` persists and validates publications; `TriggerCatalog` and `NativeEventCatalog` present available triggers, `NativeEventSubscriptions` compiles enabled event subscriptions, `EventStore` records scoped occurrences, and `DeliveryStore` plus background jobs enforce recipient-channel idempotency. Controllers enforce edit permission, CSRF and read eligibility. `AppInfo` registers the Nextcloud hooks and navigation.

## Native events and third-party compatibility

Applications are included by discovery from enabled Nextcloud apps; the production code does not inspect `LibreSign` classes directly and has no LibreSign-specific adapter. The source event class determines event meaning; a user-authenticated event can be scoped to that current principal, but must not invent another signer or file owner from arbitrary event metadata.

A dedicated **compatibility test** exercises LibreSign's `SigningRequestCompletedEvent` and a synthetic state transition through the **generic** listener and delivery paths. It does **not** claim to prove a real PDF was signed. A separate end-to-end signing flow is needed before claiming this external workflow is certified for customer delivery.

## Delivery and security

Delivery uses Nextcloud background jobs, isolated records per publication/activation/recipient/channel, and checks eligibility and preferences at sending/read boundaries. Modal/banner are alternative contextual presentations. Notification and external channels may be combined with optional Inbox storage. Anonymous banners require an explicit safe-public configuration; they never expose user lists, group membership or private message content.

`WebRouteCatalog` depends on Nextcloud internal routing metadata. This is an isolated compatibility boundary, not a guaranteed Nextcloud public API. Use conservative route filters and regression tests on new Nextcloud versions.
