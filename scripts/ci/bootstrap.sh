#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 LibreCode Coop and contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
export NC_TEST_PASSWORD="${NC_TEST_PASSWORD:-$(openssl rand -hex 16)}"
export NC_CONTAINER="${NC_CONTAINER:-communications-ci-nextcloud}"
export NC_PORT="${NC_PORT:-18884}"
export NC_BASE_URL="http://127.0.0.1:${NC_PORT}"
export NC_ADMIN_USER="qa_admin" NC_RECIPIENT_USER="poc_second" NC_UNRELATED_USER="qa_unrelated"
DB="${1:-sqlite}"
case "$DB" in sqlite|pgsql|mariadb) ;; *) echo 'Choose sqlite, pgsql or mariadb' >&2; exit 2;; esac
: "${LIBRESIGN_REF:=041d0cc4bcb810fb7403814a3fac692d9db8f69d}"
root="${NC_RUNTIME_DIR:-$PWD/.ci/runtime}"
export NC_RUNTIME_DIR="$root"
mkdir -p "$root"
NC_EXPECTED_VERSION="$(python3 - <<'PYXML'
import xml.etree.ElementTree as ET

manifest = ET.parse('appinfo/info.xml').getroot()
version = manifest.find('./dependencies/nextcloud')
if version is None or not version.get('max-version'):
    raise ValueError('The Nextcloud version range is missing in appinfo/info.xml')
print(version.get('max-version'))
PYXML
)"
if [[ ! -f "$root/nextcloud/version.php" ]]; then
  if [[ -n "${NC_SERVER_LOCAL_SOURCE:-}" ]]; then
    mkdir -p "$root/nextcloud"
    rsync -rl --exclude '/config/' --exclude '/data/' --exclude '/custom_apps/' "$NC_SERVER_LOCAL_SOURCE/" "$root/nextcloud/"
  else
    archive_url="${NC_SERVER_ARCHIVE_URL:-https://download.nextcloud.com/server/daily/latest-master.tar.bz2}"
    # Fail closed if the upstream archive is corrupted or lacks a digest.
    curl --fail --location --retry 3 --show-error --output "$root/nextcloud.tar.bz2" "$archive_url"
    curl --fail --location --retry 3 --show-error --output "$root/nextcloud.sha256" "${archive_url}.sha256"
    expected_sha="$(awk 'NR == 1 { print $1 }' "$root/nextcloud.sha256")"
    [[ "$expected_sha" =~ ^[a-fA-F0-9]{64}$ ]] || { echo 'Invalid Nextcloud archive checksum' >&2; exit 3; }
    printf '%s  %s\n' "$expected_sha" "$root/nextcloud.tar.bz2" | sha256sum --check --status
    tar -xjf "$root/nextcloud.tar.bz2" -C "$root"
    rm "$root/nextcloud.tar.bz2" "$root/nextcloud.sha256"
  fi
fi
if ! grep -qE '\$OC_Version[[:space:]]*=[[:space:]]*(\[|array\()[[:space:]]*'"$NC_EXPECTED_VERSION"',' "$root/nextcloud/version.php"; then
  echo "Nextcloud version mismatch: app manifest requires $NC_EXPECTED_VERSION" >&2
  head -n 6 "$root/nextcloud/version.php" >&2
  exit 2
fi
if [[ "${INSTALL_LIBRESIGN:-0}" == '1' && ! -f "$root/libresign/appinfo/info.xml" ]]; then
  if [[ -n "${LIBRESIGN_LOCAL_SOURCE:-}" ]]; then
    mkdir -p "$root/libresign"
    rsync -rl "$LIBRESIGN_LOCAL_SOURCE/" "$root/libresign/"
  else
    git clone -q --recurse-submodules https://github.com/LibreSign/libresign.git "$root/libresign"
    git -C "$root/libresign" checkout -q "$LIBRESIGN_REF"
    git -C "$root/libresign" submodule update --init --recursive
    docker run --rm --user "$(id -u):$(id -g)" -v "$root/libresign:/app" \
      -w /app composer:2 composer install --no-dev --no-interaction --no-progress
    # A source checkout is not a release artifact: build the frontend assets
    # that the Nextcloud app needs in order to load its signing UI.
    (cd "$root/libresign" && npm ci && npm run build)
  fi
fi
mkdir -p "$root/nextcloud/config" "$root/nextcloud/data" "$root/nextcloud/custom_apps" "$root/libresign"
# The test container runs as www-data (uid 33). Only mutable Nextcloud
# directories need ownership changes; the checked-out source stays read-only.
docker compose -f tests/ci/compose.yml -p "${NC_COMPOSE_PROJECT:-communications-ci}" up -d --no-deps nextcloud
if [[ "$DB" == pgsql ]]; then
  docker compose -f tests/ci/compose.yml -p "${NC_COMPOSE_PROJECT:-communications-ci}" --profile postgresql up -d postgres
  DB_SERVICE=postgres
elif [[ "$DB" == mariadb ]]; then
  docker compose -f tests/ci/compose.yml -p "${NC_COMPOSE_PROJECT:-communications-ci}" --profile mariadb up -d mariadb
  DB_SERVICE=mariadb
else
  DB_SERVICE=''
fi
docker exec -u 0 "$NC_CONTAINER" chown -R 33:33 /var/www/html/config /var/www/html/data /var/www/html/apps
docker exec -u 0 "$NC_CONTAINER" chmod 0770 /var/www/html/config /var/www/html/data
# Nextcloud from a development archive may not have a writable custom app
# path configured by default. Keep the app source mounted independently.
docker exec -u 0 "$NC_CONTAINER" sh -c 'cat > /var/www/html/config/99-communications-ci.config.php <<"PHP"
<?php
$CONFIG = ["apps_paths" => [
 ["path" => "/var/www/html/apps", "url" => "/apps", "writable" => false],
 ["path" => "/var/www/html/custom_apps", "url" => "/custom_apps", "writable" => true],
]];
PHP
chown www-data:www-data /var/www/html/config/99-communications-ci.config.php'
if [[ -n "$DB_SERVICE" ]]; then
  healthy=''
  for attempt in $(seq 1 65); do
    healthy="$(docker compose -f tests/ci/compose.yml -p "${NC_COMPOSE_PROJECT:-communications-ci}" ps --format json "$DB_SERVICE" | grep -o '"Health":"healthy"' || true)"
    [[ -n "$healthy" ]] && break
    sleep 2
  done
  [[ -n "$healthy" ]] || { echo "$DB_SERVICE not healthy" >&2; exit 3; }
fi
install=(php occ maintenance:install --admin-user qa_admin --admin-pass "$NC_TEST_PASSWORD" --data-dir /var/www/html/data)
case "$DB" in
  sqlite) install+=(--database sqlite);;
  pgsql) install+=(--database pgsql --database-name nextcloud --database-host postgres --database-user nextcloud --database-pass "$NC_TEST_PASSWORD");;
  mariadb) install+=(--database mysql --database-name nextcloud --database-host mariadb --database-user nextcloud --database-pass "$NC_TEST_PASSWORD");;
esac
if ! docker exec -u www-data "$NC_CONTAINER" php occ status --output json 2>/dev/null | grep -F '"installed":true' >/dev/null; then
  docker exec -u www-data "$NC_CONTAINER" "${install[@]}" >/dev/null
fi
docker exec -u www-data "$NC_CONTAINER" php occ config:system:set trusted_domains 1 --value localhost >/dev/null
docker exec -u www-data "$NC_CONTAINER" php occ config:system:set trusted_domains 2 --value 127.0.0.1 >/dev/null
# Disable only the disposable lab's first-login wizard: its modal intercepts
# Playwright clicks before the application UI can be exercised. This is a
# test-fixture setting and does not change Communications or production setup.
installed_apps="$(docker exec -u www-data "$NC_CONTAINER" php occ app:list --output json)"
if printf '%s' "$installed_apps" | python3 -c 'import json,sys; sys.exit("firstrunwizard" not in json.load(sys.stdin).get("enabled", {}))'; then
  docker exec -u www-data "$NC_CONTAINER" php occ app:disable firstrunwizard
fi
docker exec -u www-data "$NC_CONTAINER" php occ app:enable communications
if [[ "${INSTALL_LIBRESIGN:-0}" == 1 ]]; then
  docker exec -u www-data "$NC_CONTAINER" php occ app:enable libresign
fi
for uid in poc_second qa_unrelated; do
  if ! docker exec -u www-data "$NC_CONTAINER" php occ user:info "$uid" >/dev/null 2>&1; then
    # Nextcloud can create the user and still report a hook failure. Never
    # retry a potentially successful creation blindly; verify the account.
    if ! docker exec -u www-data -e OC_PASS="$NC_TEST_PASSWORD" "$NC_CONTAINER" \
      php occ user:add --password-from-env "$uid" >/dev/null; then
      docker exec -u www-data "$NC_CONTAINER" php occ user:info "$uid" >/dev/null
    fi
  fi
done
docker exec -u www-data "$NC_CONTAINER" php occ status --output json
if [[ -n "${GITHUB_ENV:-}" ]]; then
  echo "::add-mask::$NC_TEST_PASSWORD"
  for name in NC_BASE_URL NC_CONTAINER NC_TEST_PASSWORD NC_ADMIN_USER NC_RECIPIENT_USER NC_UNRELATED_USER; do
    printf '%s=%s\n' "$name" "${!name}" >> "$GITHUB_ENV"
  done
fi
printf "Nextcloud ${NC_EXPECTED_VERSION} isolated test lab ready: db=%s libresign=%s\n" "$DB" "${INSTALL_LIBRESIGN:-0}"
