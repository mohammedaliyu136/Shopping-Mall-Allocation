#!/usr/bin/env bash
# Prepares and starts the application inside the container.
set -euo pipefail

cd /app

# Hosting platforms inject configuration as environment variables; Laravel is
# happy without a .env file, but artisan needs one to write a generated key to.
[ -f .env ] || cp .env.example .env

# Generate an application key unless one was supplied as an environment
# variable. A platform-provided APP_KEY survives restarts; a generated one does
# not, which is fine for a demonstration instance.
if [ -z "${APP_KEY:-}" ] && ! grep -qE '^APP_KEY=.+' .env; then
    php artisan key:generate --no-interaction --force
fi

DB_PATH="${DB_DATABASE:-/app/database/database.sqlite}"
mkdir -p "$(dirname "$DB_PATH")"
touch "$DB_PATH"

# Load the schema and demonstration data on first boot. On a platform with an
# ephemeral filesystem this runs on every restart, which is what makes the demo
# reset itself; attach a persistent disk to keep changes between restarts.
if [ "$(php artisan tinker --execute='echo \Illuminate\Support\Facades\Schema::hasTable("allocations") ? 1 : 0;' 2>/dev/null | tail -1 | tr -d '[:space:]')" = "1" ]; then
    echo "==> Existing database found; applying any new migrations."
    php artisan migrate --force --no-interaction
else
    echo "==> Fresh database; loading schema and demonstration data."
    php artisan migrate:fresh --seed --force --no-interaction
fi

php artisan config:cache
php artisan route:cache

PORT="${PORT:-8000}"
echo "==> Serving on port ${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}"
