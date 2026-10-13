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

echo "Fair Value: open the PORTS tab → port 8000 (globe icon). Admin panel: /admin (the login is shown on the sign-in page)"
exec php artisan serve --host=0.0.0.0 --port=8000
