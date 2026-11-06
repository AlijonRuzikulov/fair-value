#!/usr/bin/env bash
# Starts the site on port 8000 and keeps it running in this terminal.
# Waits for setup.sh to finish when the codespace has just been created.
cd "$(dirname "$0")/.."

if [ ! -f .devcontainer/.ready ]; then
    echo "Waiting for the installation to finish (2–5 minutes)…"
    for _ in $(seq 1 450); do
        [ -f .devcontainer/.ready ] && break
        sleep 2
    done
fi

if [ ! -f .devcontainer/.ready ]; then
    echo
    echo "The installation did not finish. Run it again to see the error:"
    echo "    bash .devcontainer/setup.sh"
    exit 1
fi

# A second editor window must not start a second server.
if php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'; then
    echo "The site is already running on port 8000."
    exit 0
fi

# Whether the first administrator still has the first password of .env (which the
# sign-in page shows on a trial copy). When in doubt it is taken as yes.
uses_initial_password() {
    local email password
    email=$(grep '^ADMIN_EMAIL=' .env | cut -d= -f2- | tr -d '"')
    password=$(grep '^ADMIN_PASSWORD=' .env | cut -d= -f2- | tr -d '"')
    [ -n "$email" ] && [ -n "$password" ] || return 1
    php -r '
        try {
            $db = new PDO("sqlite:database/database.sqlite");
            $query = $db->prepare("select password from users where email = ?");
            $query->execute([$argv[1]]);
            $hash = $query->fetchColumn();
            // No such administrator: the sign-in page shows nothing.
            exit($hash !== false && password_verify($argv[2], $hash) ? 0 : 1);
        } catch (Throwable $e) {
            exit(0);
        }' "$email" "$password"
}

# By default only the codespace owner can open the link; make it open for
# the people it is sent to (once the server is listening), but not while the
# sign-in page still shows the first password: anyone with the link could
# then sign in as the administrator.
if [ -n "${CODESPACE_NAME:-}" ] && command -v gh > /dev/null; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
    (
        sleep 5
        if uses_initial_password; then
            echo
            echo "The link opens for other people once the first password is changed:"
            echo "sign in, then your name (top right) → My profile → new password."
            while uses_initial_password; do sleep 15; done
        fi
        if gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" > /dev/null 2>&1; then
            echo
            echo "Link for other people (works while this codespace is running): $url"
        else
            echo
            echo "To share the link: PORTS tab → right-click port 8000 → Port Visibility → Public. Link: $url"
        fi
    ) &
fi

echo "Fair Value: open the PORTS tab → port 8000 (globe icon). Admin panel: /admin (the login is shown on the sign-in page)"
# Several workers: the map tiles and the pages are served at the same time.
PHP_CLI_SERVER_WORKERS=4 exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
