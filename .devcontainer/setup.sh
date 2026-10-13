#!/usr/bin/env bash
# Prepares a demo copy of the site: dependencies, .env, SQLite database
# with sample data. Runs when the codespace is created; safe to run again.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f .devcontainer/.ready

composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
    cp .env.example .env
fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

# Codespaces serves the app through an HTTPS proxy on its own domain.
if [ -n "${CODESPACE_NAME:-}" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
    sed -i "s|^APP_URL=.*|APP_URL=${url}|" .env
fi
set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}
set_env TRUSTED_PROXIES '*'
set_env DEMO_MODE true

touch database/database.sqlite
php artisan migrate:fresh --seed --seeder=DemoSeeder --force
php artisan optimize:clear > /dev/null

touch .devcontainer/.ready
echo
echo "Demo is ready. Admin panel: /admin — demo@fairvalue.uz / demo12345"
