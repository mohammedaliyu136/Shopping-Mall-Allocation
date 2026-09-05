#!/usr/bin/env bash
# Prepares the application the first time the container is created.
# Runs once; serve.sh then starts the web server on every container start.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist --no-progress

echo "==> Preparing the environment file"
[ -f .env ] || cp .env.example .env

# Only generate a key if the file does not already carry one.
grep -qE '^APP_KEY=.+' .env || php artisan key:generate --no-interaction

echo "==> Creating the SQLite database and loading the demonstration data"
touch database/database.sqlite
php artisan migrate:fresh --seed --no-interaction

cat <<'BANNER'

====================================================================
  Ready.

  The application starts automatically on port 8000. Open the
  forwarded URL from the PORTS tab.

  Sign in with any of these (password: password)

    admin@mall.test              Super Administrator
    mall.admin@mall.test         Mall Administrator
    allocation@mall.test         Allocation Officer
    finance@mall.test            Finance Officer
    support@mall.test            Customer Support Officer
    auditor@mall.test            Auditor (read-only)
    aisha.bello@example.test     Shop Owner (owner portal)

  /verify is the public verification portal and needs no login.
====================================================================

BANNER
