#!/usr/bin/env bash
# Puts Fair Value on a fresh Ubuntu server (22.04 or 24.04), for example the
# free Oracle Cloud server, with one command; running it again updates it.
#
#     curl -fsSL https://raw.githubusercontent.com/AlijonRuzikulov/fair-value/claude/amazing-bohr-4qrtrr/server/install.sh | sudo bash
#
# The site gets the address https://<server-ip-with-dashes>.sslip.io (a free
# address that needs no domain; Caddy takes its https certificate by itself).
# With a domain pointed at the server: ... | sudo DOMAIN=fairvalue.uz bash
#
# Installs PHP 8.3 (php-fpm), Composer, Caddy, the system in /var/www/fair-value
# with an SQLite database, and a cron line for the schedule and the queue.
# Entered data is kept when it is run again.
set -euo pipefail

BRANCH="${BRANCH:-claude/amazing-bohr-4qrtrr}"
REPO="${REPO:-https://github.com/AlijonRuzikulov/fair-value.git}"
DIR=/var/www/fair-value
export DEBIAN_FRONTEND=noninteractive

if [ "$(id -u)" != 0 ]; then
    echo "Run it with sudo: ... | sudo bash"
    exit 1
fi

say() { printf '\n==> %s\n' "$1"; }

# --- The address -----------------------------------------------------------
if [ -z "${DOMAIN:-}" ]; then
    if [ -f "$DIR/.env" ] && grep -q '^APP_URL=https://' "$DIR/.env"; then
        DOMAIN=$(grep '^APP_URL=' "$DIR/.env" | cut -d= -f2- | sed -e 's|^https://||' -e 's|/.*$||' -e 's|"||g')
    else
        IP=$(curl -fsS --max-time 10 https://api.ipify.org || curl -fsS --max-time 10 https://ifconfig.me)
        [ -n "$IP" ] || { echo "The public IP address of the server could not be found."; exit 1; }
        DOMAIN="$(echo "$IP" | tr . -).sslip.io"
    fi
fi
say "Address: https://$DOMAIN"

# --- Memory: a small server needs swap for Composer ------------------------
if [ "$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)" -lt 1900 ] && [ -z "$(swapon --show 2> /dev/null || true)" ]; then
    say "Adding 2 GB of swap"
    fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048
    chmod 600 /swapfile && mkswap /swapfile > /dev/null && swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# --- Packages ----------------------------------------------------------------
say "Installing PHP 8.3, Composer and Caddy"
apt-get update -qq
apt-get install -y -qq software-properties-common curl git unzip sqlite3 ca-certificates gnupg debian-keyring debian-archive-keyring apt-transport-https > /dev/null
if ! apt-cache show php8.3-fpm > /dev/null 2>&1; then
    add-apt-repository -y ppa:ondrej/php > /dev/null
    apt-get update -qq
fi
apt-get install -y -qq php8.3-cli php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    php8.3-gd php8.3-intl php8.3-bcmath > /dev/null

if ! command -v composer > /dev/null; then
    curl -fsSL https://getcomposer.org/installer | php -- --quiet --install-dir=/usr/local/bin --filename=composer
fi

if ! command -v caddy > /dev/null; then
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor --yes -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
    curl -fsSL 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
    apt-get update -qq
    apt-get install -y -qq caddy > /dev/null
fi

# --- Firewall: Oracle's Ubuntu images let only SSH in ------------------------
RULES=$(iptables -S INPUT 2> /dev/null || true)
if [[ "$RULES" == *"-j REJECT"* ]]; then
    for port in 80 443; do
        iptables -C INPUT -p tcp --dport "$port" -m state --state NEW -j ACCEPT 2> /dev/null \
            || iptables -I INPUT 1 -p tcp --dport "$port" -m state --state NEW -j ACCEPT
    done
    if command -v netfilter-persistent > /dev/null; then
        netfilter-persistent save > /dev/null 2>&1 || true
    fi
fi

# --- The system --------------------------------------------------------------
say "Taking the system from GitHub"
git config --global --add safe.directory "$DIR"
if [ -d "$DIR/.git" ]; then
    git -C "$DIR" fetch --quiet origin "$BRANCH"
    git -C "$DIR" checkout --quiet "$BRANCH"
    git -C "$DIR" reset --quiet --hard "origin/$BRANCH"
else
    mkdir -p "$(dirname "$DIR")"
    git clone --quiet --depth 1 -b "$BRANCH" "$REPO" "$DIR"
fi
cd "$DIR"

FIRST_PASSWORD=""
if [ ! -f .env ]; then
    cp .env.example .env
    FIRST_PASSWORD=$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 14 || true)
    sed -i \
        -e 's|^APP_ENV=.*|APP_ENV=production|' \
        -e 's|^APP_DEBUG=.*|APP_DEBUG=false|' \
        -e 's|^LOG_LEVEL=.*|LOG_LEVEL=warning|' \
        -e 's|^DEMO_MODE=.*|DEMO_MODE=false|' \
        -e 's|^SESSION_SECURE_COOKIE=.*|SESSION_SECURE_COOKIE=true|' \
        -e "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" \
        -e "s|^ADMIN_PASSWORD=.*|ADMIN_PASSWORD=$FIRST_PASSWORD|" \
        .env
fi
grep -q '^APP_URL=https://' .env || sed -i "s|^APP_URL=.*|APP_URL=https://$DOMAIN|" .env
if command -v chromium > /dev/null || command -v chromium-browser > /dev/null; then
    grep -q '^PDF_CHROME=' .env || echo "PDF_CHROME=$(command -v chromium || command -v chromium-browser)" >> .env
fi
touch database/database.sqlite

say "Installing the libraries and the database"
export COMPOSER_ALLOW_SUPERUSER=1
bash deploy.sh  # Composer, key, migrations, first administrator, caches
chown -R www-data:www-data "$DIR"

# --- Web server, schedule, queue --------------------------------------------
say "Setting up the web server"
cat > /etc/caddy/Caddyfile <<CADDY
$DOMAIN {
    root * $DIR/public
    encode gzip
    request_body {
        max_size 64MB
    }
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
CADDY
cat > /etc/php/8.3/fpm/conf.d/99-fair-value.ini <<'INI'
upload_max_filesize = 12M
post_max_size = 64M
memory_limit = 256M
INI
# Caddy talks to php-fpm through a socket that only the www-data group may use.
usermod -aG www-data caddy 2> /dev/null || true
systemctl enable --now php8.3-fpm > /dev/null 2>&1
systemctl restart php8.3-fpm
systemctl enable caddy > /dev/null 2>&1
systemctl restart caddy

cat > /etc/cron.d/fair-value <<CRON
* * * * * www-data cd $DIR && php artisan schedule:run > /dev/null 2>&1
* * * * * www-data cd $DIR && php artisan queue:work --stop-when-empty --max-time=50 > /dev/null 2>&1
CRON
chmod 644 /etc/cron.d/fair-value

say "Done"
echo "Link:        https://$DOMAIN/admin"
if [ -n "$FIRST_PASSWORD" ]; then
    echo "Sign in:     $(grep '^ADMIN_EMAIL=' .env | cut -d= -f2-)  /  $FIRST_PASSWORD"
    echo "Change this password after signing in: your name (top right) -> My profile."
fi
echo "The first opening may take a minute while the https certificate is issued."
echo "To update later, run the same command again."
