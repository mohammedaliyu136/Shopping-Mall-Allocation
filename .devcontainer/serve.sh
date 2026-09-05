#!/usr/bin/env bash
# Starts the web server in the background on every container start, so the
# forwarded port is live without anyone having to type a command.
set -euo pipefail

cd "$(dirname "$0")/.."

# Nothing to serve until setup.sh has run.
[ -f vendor/autoload.php ] || exit 0

# Don't start a second copy if one is already listening.
if pgrep -f "artisan serve --host=0.0.0.0 --port=8000" > /dev/null 2>&1; then
    echo "Server already running on port 8000."
    exit 0
fi

mkdir -p storage/logs
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &

echo "Serving on port 8000 (log: storage/logs/serve.log)."
