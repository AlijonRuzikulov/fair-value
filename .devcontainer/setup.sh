#!/usr/bin/env bash
# Prepares a demo copy of the site: dependencies, .env, SQLite database
# with sample data. Runs once when the codespace is created.
set -euo pipefail
cd "$(dirname "$0")/.."

composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# Codespaces serves the app through an HTTPS proxy on its own domain.
if [ -n "${CODESPACE_NAME:-}" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
    sed -i "s|^APP_URL=.*|APP_URL=${url}|" .env
fi
if grep -q '^TRUSTED_PROXIES=' .env; then
    sed -i 's|^TRUSTED_PROXIES=.*|TRUSTED_PROXIES=*|' .env
else
    echo 'TRUSTED_PROXIES=*' >> .env
fi

touch database/database.sqlite
php artisan migrate:fresh --seed --seeder=DemoSeeder --force

echo
echo "Demo is ready. Admin panel: /admin — demo@fairvalue.uz / demo12345"
