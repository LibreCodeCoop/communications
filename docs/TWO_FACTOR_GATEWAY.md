<!-- SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
SPDX-License-Identifier: AGPL-3.0-or-later -->
# Two-Factor Gateway: optional messaging and recipient consent

Communications 0.29.0 can use the optional Nextcloud **Two-Factor Gateway** app for direct SMS and messaging delivery. This is experimental integration on Nextcloud 36 development builds; it does not modify the recipient's authentication or two-factor settings.

## Integration contract

Communications resolves the installed app's `OCA\TwoFactorGateway\Service\GatewayDirectIntegrationService` and uses these methods:

- `ensureAvailable(gatewayId)`
- `isGatewayComplete(gatewayId)`
- `send(gatewayId, identifier, message)`

Supported gateway IDs are `sms`, `signal`, `telegram`, `gowhatsapp`, `whatsappbusiness` and `xmpp`. These are **direct-provider identifiers**, not a recipient's selected 2FA authentication method. The application does not call messaging-provider APIs directly or reuse OTP codes.

A provider must be installed, enabled and fully configured under Nextcloud **Administration > Security** before it becomes selectable. A group-level 2FA configuration without a complete direct provider is not enough. The catalog distinguishes an absent gateway app from an installed but unconfigured provider.

## Recipient setup and consent

1. An administrator enables Two-Factor Gateway and configures an appropriate direct provider.
2. Each recipient opens **Communications > Inbox > Notification preferences**, enables the desired provider, enters a valid destination and saves.
3. Consent to receive **sponsored** messages is separate and is disabled by default.
4. An editor selects one or more available providers for a publication. The editor cannot read recipients' private destination settings.

The preferences are stored in an independent per-user Communications setting, not in the user's two-factor authentication profile. Disabling a channel or replacing the preferences without that channel removes its stored destination. A publication never treats a person's 2FA enrollment as messaging consent.

The API is `GET/POST /api/gateway-preferences`. For POST, `channels` is a mapping keyed by gateway ID, with `enabled` (boolean), `destination` (string) and `sponsored` (boolean). The operation **replaces the entire preference set**, so callers must include every channel to retain. Example:

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

## Supported destination formats

| Provider | Valid destination |
| --- | --- |
| SMS, Signal, WhatsApp, WhatsApp Business | E.164-style telephone number such as `+15551234567` |
| Telegram | Positive numeric private chat identifier, 5-20 digits; the recipient must initialize a bot conversation where required |
| XMPP | JID such as `recipient@example.org` |

The Telegram preference does **not** accept a username, a bot API token, or negative group/channel identifiers. Actual provider configuration and delivery eligibility still depend on the installed gateway implementation and its policies.

## Processing and failure semantics

For each eligible recipient and selected provider, Communications checks the publication audience, channel readiness, independent recipient consent, destination validity and (for sponsored messages) the additional sponsored-consent flag. Outbound messages are queued through the publication/event delivery path and use the same per-recipient/channel idempotency safeguards as the other channels.

External transports may fail or return an indeterminate outcome after accepting a message. The outbox supports retries, but cannot promise exactly-once delivery or proof that an end user read the message. Provider errors must not leak destination identifiers or private message contents in logs.

## Verification boundaries

The development contract exercises provider discovery, preference validation and the service API through controlled tests and stubs. No real external provider, contact destination or production account is required for the regular CI. A fully configured isolated provider and consented test recipient are prerequisites for separate real-transport verification.

See [Delivery channels](DELIVERY_CHANNELS.md), [API](API.md) and [Testing and evidence](TESTING.md).
