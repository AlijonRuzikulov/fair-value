#!/usr/bin/env bash
# Prepares a trial copy of the system: dependencies, .env and an empty
# SQLite database with the first administrator. Runs when the codespace is
# created; safe to run again — entered data is kept.
set -euo pipefail
cd "$(dirname "$0")/.."
rm -f .devcontainer/.ready

composer install --no-interaction --prefer-dist --no-progress

if [ ! -f .env ]; then
    cp .env.example .env
fi
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force

set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}

# Codespaces serves the app through an HTTPS proxy on its own domain.
if [ -n "${CODESPACE_NAME:-}" ]; then
    set_env APP_URL "https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
fi
set_env TRUSTED_PROXIES '*'
# The sign-in page shows the first login until the password is changed.
set_env DEMO_MODE true
grep -q '^ADMIN_EMAIL=.' .env || set_env ADMIN_EMAIL admin@fairvalue.uz
grep -q '^ADMIN_PASSWORD=.' .env || set_env ADMIN_PASSWORD admin12345

touch database/database.sqlite

# Earlier versions of this script filled the database with sample clients
# and objects; such a database is emptied once. Real data is never touched.
if php -r 'try { $db = new PDO("sqlite:database/database.sqlite"); exit($db->query("select count(*) from users where email = '\''demo@fairvalue.uz'\''")->fetchColumn() ? 0 : 1); } catch (Throwable $e) { exit(1); }'; then
    echo "Removing the sample data…"
    php artisan migrate:fresh --force
fi

php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear > /dev/null

touch .devcontainer/.ready
echo
echo "Ready. Admin panel: /admin — $(grep '^ADMIN_EMAIL=' .env | cut -d= -f2-) / $(grep '^ADMIN_PASSWORD=' .env | cut -d= -f2-)"
