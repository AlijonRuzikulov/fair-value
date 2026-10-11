#!/usr/bin/env bash
# Starts the web server in the background on port 8000.
cd "$(dirname "$0")/.."
pkill -f "artisan serve" 2>/dev/null || true
nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
