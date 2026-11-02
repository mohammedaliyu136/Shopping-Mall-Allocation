#!/usr/bin/env bash
# Starts the web server in the background on every Codespace start.
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f vendor/autoload.php ] || exit 0
# Match only the PHP server process itself, not shells that mention it.
pgrep -f "^php artisan serve --host=0.0.0.0 --port=8000" > /dev/null 2>&1 && exit 0
mkdir -p storage/logs
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
echo "Serving on port 8000."
