#!/usr/bin/env bash
# First-time setup for a Codespace: dependencies, .env, database and demo data.
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-interaction --prefer-dist --no-progress
[ -f .env ] || cp .env.example .env
grep -qE '^APP_KEY=.+' .env || php artisan key:generate --no-interaction
touch database/database.sqlite
php artisan migrate:fresh --seed --no-interaction

cat <<'BANNER'

====================================================================
  Ward View is ready. It starts automatically on port 8000;
  open the forwarded address from the PORTS tab.

  Password for every account: password

    admin@hospital.test          Admin (ward setup screen)
    dr.okafor@hospital.test      Doctor  - Medical Ward A
    nurse.bello@hospital.test    Nurse   - Medical Ward A
    dr.adeyemi@hospital.test     Doctor  - ICU
    nurse.yusuf@hospital.test    Nurse   - ICU
    dr.eze@hospital.test         Doctor  - A&E
    nurse.danjuma@hospital.test  Nurse   - A&E
====================================================================
BANNER
