<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# Communications API (experimental)

This document describes the **Communications** Nextcloud app (`communications`), version 0.29.0, on Nextcloud 36 development builds. This API is not a stable integration contract. All URLs below are relative to `/index.php/apps/communications`. Test integrations against an isolated instance.

## Authentication and access control

- Publication management and catalog endpoints require a Nextcloud session with editorial permission: an administrator or a member of a configured editor group.
- Only administrators can read or change editorial group delegation.
- Inbox, delivery, gateway-preference and ordinary user-preference endpoints operate on the authenticated user's session. Callers cannot supply a different account UID to impersonate a recipient. Mutating session endpoints require the normal Nextcloud CSRF request token.
- `GET /api/public-banners` exposes only the restricted anonymous banner projection; `POST /api/hooks/event` is the only unauthenticated write endpoint and requires an HMAC signature. Neither grants anonymous access to private publications.
- Do not place API secrets, external document identifiers or recipient credentials in publication content.

## Discovery and editor permissions

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/catalog` | Available triggers, automation intents, sources, applications, placements, delivery channels and editorial settings |
| GET | `/api/routes?app=files` | Server-filtered navigable GET route patterns for an available app |
| GET | `/api/users?search=...` | Restricted user suggestions for publication audiences |
| GET | `/api/editor-groups` | Current editor delegation; administrator only |
| POST | `/api/editor-groups` | Replace delegated editor groups; administrator only |

`/api/catalog` returns `automation.intents`, `automation.sources`, `automation.applications`, `automation.placements`, `deliveryChannels`, `groups`, `statuses` and `presentations`. The `triggers` array is retained for older clients; use the intent/source catalog for new editor flows. Event sources can come from installed apps without a Communications-specific adapter. Display labels depend on the Nextcloud locale; integrate using stable IDs, not localized text.

A page trigger (`trigger_key=nextcloud.page.opened`) requires a concrete navigable `placement` and, optionally, an exact `page_route` pattern returned by `/api/routes`. An empty `page_route` matches any supported page in that app, not every page in Nextcloud. The route discovery is heuristic and uses Nextcloud internals: an API endpoint or a concrete user-specific URL is not a valid route-pattern substitute.

## Publication management

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/campaigns` | List publications visible to an editor |
| GET | `/api/campaigns/{id}` | Get a publication |
| POST | `/api/campaigns` | Create a publication (HTTP 201) |
| PUT | `/api/campaigns/{id}` | Update a publication |
| POST | `/api/campaigns/{id}/status` | Set `active`, `paused`, `draft` or `archived` |
| POST | `/api/campaigns/{id}/duplicate` | Copy a publication as a draft (HTTP 201) |
| POST | `/api/campaigns/{id}/move` | Change manual order using `direction` and optional `target_id` |
| DELETE | `/api/campaigns/{id}` | Archive without deleting the record |
| POST | `/api/campaigns/{id}/purge` | Permanently remove an archived publication after matching `confirmName` |
| GET | `/api/campaigns/{id}/metrics` | Aggregated, explicitly **unaudited** delivery counters |

Example `POST /api/campaigns` body, using a recipient that exists in the test instance:

```json
{
  "name": "Security update",
  "headline": "Review the updated access policy",
  "message": "Check your sharing permissions.",
  "trigger_key": "nextcloud.board.published",
  "status": "draft",
  "presentation": "none",
  "board_visible": true,
  "audience_type": "users",
  "audience_users": ["example_user"],
  "placement": "any",
  "frequency": "once",
  "category": "institutional",
  "notify_users": false,
  "notify_activity": false,
  "notify_email": false,
  "gateway_channels": [],
  "priority": 10,
  "starts_at": null,
  "ends_at": null
}
```

`name`, `headline`, `message`, `trigger_key`, `status`, `presentation` and `audience_type` are required. The service validates target accounts/groups, registered triggers, publication status, timing and channel combinations. Invalid payloads return HTTP 422. If `audience_type=group`, supply `audience_groups`; `group_match` supports `any` or `all`, and `excluded_groups` takes precedence over inclusions. For a scheduled `nextcloud.time.window` trigger, `starts_at` is required.

`presentation` accepts `banner`, `modal`, `none` and the legacy `board` value. `board_visible` independently controls the Inbox copy. `notify_users`, `notify_activity`, `notify_email` and `gateway_channels` choose optional additional delivery channels; at least one destination is required. A `presentation=none` publication with no Inbox and no outbound channel is invalid. See [Delivery channels](DELIVERY_CHANNELS.md).

`frequency` accepts `event`, `once`, `daily`, `weekly` and `custom`. `min_interval_minutes`, `max_per_day` and `max_total` constrain repeated deliveries. Sponsored publications have a conservative default daily ceiling when the maximum is omitted. `starts_at` and `ends_at` are Unix timestamps (or null), checked on the server; the end must be after the start. The API allows safe internal paths, HTTP(S), `mailto:` and `tel:` for CTA links, with label and URL provided together.

For localization, `translated_content` is a mapping from supported locale identifiers (for example `pt_BR`) to translated `headline`, `message` and `cta_label`. Do not confuse publication-localized content with the app's Nextcloud translation catalogs.

## Recipient delivery and Inbox

| Method | Endpoint | Purpose |
| --- | --- | --- |
| GET | `/api/pending?page=files&path=...` | Diagnostic listing without taking a delivery reservation |
| POST | `/api/claim` | Reserve one eligible contextual delivery for the current tab |
| POST | `/api/claim-batch` | Reserve up to five eligible contextual deliveries (`limit`, maximum 5) |
| POST | `/api/impression` | Record presentation of a reserved delivery |
| POST | `/api/ack` | Acknowledge or dismiss a reserved delivery |
| POST | `/api/click` | Record CTA interaction with a reserved delivery |
| GET | `/api/preferences` | Current user's `sponsoredOptout` setting |
| POST | `/api/preferences` | Set `sponsoredOptout` |
| GET | `/api/board` | Paginated recipient Inbox; supports `page`, `search` and `filter=unread` |
| GET | `/api/board/{id}` | Authorized details of an Inbox or eligible outbound publication |
| POST | `/api/board/{id}/read` | Mark an Inbox item as read |
| GET | `/api/board/{id}/comments` | Visible comments for that item |
| POST | `/api/board/{id}/comments` | Add a comment |
| DELETE | `/api/board/{id}/comments/{commentId}` | Delete an authorized comment |
| GET | `/api/board/{id}/mentions?search=...` | Authorized mention suggestions |
| GET | `/api/public-banners` | Restricted, read-only anonymous banner feed |

Example claim input:

```json
{"page": "files", "path": "/index.php/apps/files/recent", "newVisit": true}
```

The server identifies the recipient from the authenticated session. The claim response contains `campaign`, which is null if no delivery is available. For a claimed item, use the returned `deliveryId` and `claimToken` in `/api/impression`, `/api/ack` or `/api/click`:

```json
{"deliveryId": 456, "claimToken": "returned-reservation-token"}
```

A reservation is not transferable to another user or publication. Invalid transitions return HTTP 409. The `path` parameter is a client-provided navigation path without a query string or fragment; it is not independently auditable proof of human navigation. Similarly, an impression records browser feedback, **not** confirmed human attention or billable reach.

## Recipient gateway preferences

`GET /api/gateway-preferences` lists available providers and the current user's saved channel destinations and consent. `POST /api/gateway-preferences` replaces that user's entire set of channel preferences. It expects a keyed `channels` object, for example:

```json
{
  "channels": {
    "telegram": {
      "enabled": true,
      "destination": "123456789",
      "sponsored": false
    }
  }
}
```

Only supported configured channels can actually send messages. Omitting or disabling a channel removes its stored destination. Consent for sponsored content is separate. See [Two-Factor Gateway and consent](TWO_FACTOR_GATEWAY.md).

## HMAC-authenticated incoming webhook

`POST /api/hooks/event` accepts one event for **one campaign and one existing Nextcloud UID**. It is not a general broadcast endpoint.

- Configure the sensitive `communications` app setting `webhook_secret` on the server with at least 32 characters. Without it, requests receive HTTP 503.
- Send raw JSON (at most 8192 bytes) and headers `X-CC-Timestamp: <Unix-seconds>` and `X-CC-Signature: sha256=<lowercase-hex-HMAC>`.
- Compute the signature over the exact byte string `timestamp + "." + raw_request_body`, using HMAC-SHA256 and the secret.
- Timestamps must fall within 300 seconds of server time.
- Require `campaignId` (a positive integer, referencing an active `external.webhook` publication), `uid` (one existing account) and `eventId` (a nonempty external identifier).

Example body:

```json
{"campaignId": 42, "uid": "example_user", "eventId": "external-event-2026-001"}
```

HTTP 202 records the accepted event; invalid signatures return 401, invalid recipients/campaigns/events return 422, oversize bodies return 400, and server processing errors return 500. An external event identifier is deduplicated for the campaign/recipient context. Audience authorization and delivery frequency remain enforced. A shared HMAC key is **not** per-tenant authorization, and production integrations would need scoped credentials, revocation, rotation and rate limits.

## Compatibility and limitations

- `GET /api/routes` uses Nextcloud internal route metadata and must be revalidated after server upgrades.
- Nextcloud native app events and the webhook are different trust boundaries; an internal event is not proof that an external PDF was signed.
- Anonymous banners must be institutional, public-to-all, time-scheduled and free of private outbound channels.
- Delivery counters, impressions and browser-reported navigation are not audited billing evidence.
- This experimental API and its database schema are not a migration contract for the previous `contextualcampaigns` proof of concept.
