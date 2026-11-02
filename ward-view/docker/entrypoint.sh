#!/usr/bin/env bash
# Prepares and starts the app in a container. On an ephemeral filesystem the
# demo data is rebuilt on every restart; set DEMO_PASSWORD on public hosts.
set -euo pipefail
cd /app
[ -f .env ] || cp .env.example .env
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' .env; then
    php artisan key:generate --no-interaction --force
fi
DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"
mkdir -p "$(dirname "$DB_PATH")" && touch "$DB_PATH"
if [ "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("ward_configs") ? 1 : 0;' 2>/dev/null | tail -1 | tr -d '[:space:]')" = "1" ]; then
    php artisan migrate --force --no-interaction
else
    php artisan migrate:fresh --seed --force --no-interaction
fi
php artisan config:cache && php artisan route:cache
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
