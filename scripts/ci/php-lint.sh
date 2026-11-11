#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
cd "$(dirname "$0")/../.."
find lib appinfo templates tests/php -type f -name '*.php' -print0 | xargs -0 -n 1 php -l
