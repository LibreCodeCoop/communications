#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail

if [[ $# -ne 3 ]]; then
  echo "Usage: $0 <sqlite|pgsql|mariadb> <shard-index> <shard-total>" >&2
  exit 2
fi
if [[ ! "$2" =~ ^[1-9][0-9]*$ || ! "$3" =~ ^[1-9][0-9]*$ ]] || (( 10#$2 > 10#$3 )); then
  echo "Invalid Playwright shard: $2/$3" >&2
  exit 2
fi

database="$1"
export NC_CI_DB="$database"
case "$database" in
  sqlite) export INSTALL_LIBRESIGN=1 ;;
  pgsql|mariadb) export INSTALL_LIBRESIGN=0 ;;
  *) echo "Unsupported test database: $database" >&2; exit 2 ;;
esac

cleanup() {
  status=$?
  trap - EXIT
  echo "::group::Stop disposable Nextcloud test environment"
  docker compose -f tests/ci/compose.yml -p "${NC_COMPOSE_PROJECT:-communications-ci}" --profile postgresql --profile mariadb down --volumes --remove-orphans || true
  echo "::endgroup::"
  exit "$status"
}
trap cleanup EXIT

# Source the bootstrap so its per-job credentials and container settings stay
# available to Playwright and the contract checks in this same step.
source scripts/ci/bootstrap.sh "$database"

echo "::group::Check route and database contracts"
docker exec -u www-data "$NC_CONTAINER" php /var/www/html/custom_apps/communications/tests/ci/assert-routes.php
schema_output="$(docker exec -u www-data "$NC_CONTAINER" php /var/www/html/custom_apps/communications/tests/integration/fixtures/schema_snapshot.php)"
printf '%s\n' "$schema_output"
if [[ "$schema_output" != *"Initial schema contract passed:"* ]]; then
  echo "Schema assertion did not report completion" >&2
  exit 1
fi
docker exec -u www-data "$NC_CONTAINER" php /var/www/html/custom_apps/communications/tests/integration/fixtures/canonical_contract.php
echo "::endgroup::"

npm run playwright -- --shard="$2/$3"
