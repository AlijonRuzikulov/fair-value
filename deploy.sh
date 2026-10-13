#!/usr/bin/env bash
# Installs or updates the site on a server (VPS or hosting with SSH access).
#
#     git clone -b claude/amazing-bohr-4qrtrr https://github.com/AlijonRuzikulov/fair-value.git
#     cd fair-value
#     bash deploy.sh        # first run: creates .env, fill it in and run again
#
# Later updates: git pull && bash deploy.sh
set -euo pipefail
cd "$(dirname "$0")"

composer install --no-dev --optimize-autoloader --no-interaction --no-progress

if [ ! -f .env ]; then
    cp .env.example .env
    sed -i \
        -e 's|^APP_ENV=.*|APP_ENV=production|' \
        -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
        -e 's|^LOG_LEVEL=.*|LOG_LEVEL=warning|' \
        -e 's|^DEMO_MODE=.*|DEMO_MODE=false|' \
        -e 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' \
        .env
    php artisan key:generate --force
    echo
    echo "The .env file has been created. Fill in:"
    echo "  APP_URL         the site address, e.g. https://fairvalue.uz"
    echo "  DB_*            the database (or keep DB_CONNECTION=sqlite)"
    echo "  ADMIN_EMAIL / ADMIN_PASSWORD   the first administrator"
    echo "then run again:  bash deploy.sh"
    exit 0
fi

grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
if grep -q '^APP_URL=http://localhost' .env; then
    echo "Set APP_URL in .env to the site address (e.g. https://fairvalue.uz) and run again."
    exit 1
fi

installed=$(php artisan migrate:status > /dev/null 2>&1 && echo 1 || echo 0)
if [ "$installed" = 1 ]; then
    php artisan down --retry=15 || true
    trap 'php artisan up' EXIT
fi

php artisan migrate --force
php artisan db:seed --force          # regions and the first administrator, only if missing
php artisan optimize

chmod -R ug+rwX storage bootstrap/cache 2> /dev/null || true

echo
echo "Done: $(grep '^APP_URL=' .env | cut -d= -f2-)  —  admin panel: /admin"
