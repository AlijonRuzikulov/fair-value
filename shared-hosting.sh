#!/usr/bin/env bash
# Builds the site for shared hosting without SSH or Composer (InfinityFree and
# the like), where everything must sit in the web folder (htdocs):
#
#     bash shared-hosting.sh [address of the site]
#
# The result is build/shared-hosting/htdocs (and fair-value-htdocs.zip next to
# it): the libraries, a ready SQLite database with the first administrator and
# an .env for production. Upload the contents of htdocs into the hosting's
# htdocs folder. The administrator's password is printed at the end.
set -euo pipefail
cd "$(dirname "$0")"
repo=$(pwd)
url=${1:-http://SAYTINGIZ.infinityfreeapp.com}
out=$repo/build/shared-hosting
site=$out/htdocs

if [ -e "$out" ]; then
    echo "Move or delete $out first."
    exit 1
fi
mkdir -p "$site"

# The committed files only: no tests, development tools or local data.
git archive HEAD | tar -x -C "$site"
cd "$site"
for path in tests .devcontainer .github deploy.sh shared-hosting.sh phpunit.xml .editorconfig .gitattributes CLAUDE.md; do
    if [ -e "$path" ]; then
        mkdir -p "$out/left-out"
        mv "$path" "$out/left-out/"
    fi
done

password=$(php -r 'echo substr(str_replace(["+", "/", "="], "", base64_encode(random_bytes(18))), 0, 14);')
cp .env.example .env
set_env() {
    if grep -q "^$1=" .env; then
        sed -i "s|^$1=.*|$1=$2|" .env
    else
        echo "$1=$2" >> .env
    fi
}
set_env APP_ENV production
set_env APP_DEBUG false
set_env APP_URL "$url"
set_env LOG_LEVEL warning
set_env LOG_STACK single
set_env DEMO_MODE false
# No background workers and no cron on free hosting.
set_env QUEUE_CONNECTION sync
set_env CACHE_STORE file
set_env SESSION_DRIVER database
# Turn it on once the site has an SSL certificate (https).
set_env SESSION_SECURE_COOKIE false
set_env ADMIN_EMAIL admin@fairvalue.uz
set_env ADMIN_PASSWORD "$password"

# The libraries already installed here are reused (Composer only takes the development ones away).
if [ -d "$repo/vendor" ]; then
    cp -a "$repo/vendor" vendor
fi
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
php artisan key:generate --force --no-interaction > /dev/null

: > database/database.sqlite
php artisan migrate --force --no-interaction > /dev/null
php artisan db:seed --force --no-interaction > /dev/null
php artisan optimize:clear > /dev/null

# Nothing left from the build: logs, sessions, compiled views.
find storage/logs storage/framework -type f ! -name .gitignore -delete
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs

# Behind the .htaccess of the root, the folders are also closed one by one,
# in case the hosting turns rewriting off.
for dir in app bootstrap config database lang resources routes storage vendor; do
    printf '%s\n' '<IfModule mod_authz_core.c>' '    Require all denied' '</IfModule>' '<IfModule !mod_authz_core.c>' '    Deny from all' '</IfModule>' > "$dir/.htaccess"
done

# The hosting refuses PHP files over 1 MB and other files over 10 MB.
big=$(find . -type f \( \( -name '*.php' -size +1000k \) -o -size +10000k \) | head -5)
if [ -n "$big" ]; then
    echo "Files too big for the hosting:"
    echo "$big"
fi

files=$(find . -type f | wc -l)
(cd "$site" && zip -qr -9 "$out/fair-value-htdocs.zip" .)
echo
echo "Ready: $site ($files files) and $out/fair-value-htdocs.zip"
echo "Site address in .env: $url (change APP_URL in htdocs/.env if it is different)"
echo "Administrator: admin@fairvalue.uz / $password  (change the password after signing in)"
