<?php

/**
 * SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\Communications\Service;

/**
 * Transport may have accepted an external message before losing its response.
 * Never replay automatically without provider-level idempotency.
 */
final class DeliveryOutcomeUnknownException extends \RuntimeException {
}
