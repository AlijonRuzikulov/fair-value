#!/usr/bin/env bash
# Keeps the codespace on the latest version of its branch: takes what was
# pushed to GitHub (only a fast-forward, never over changes made here by
# hand), then brings the libraries and the database up to it. Entered data
# is kept. The site picks the new code up at the next page load.
#
#     bash .devcontainer/update.sh           # once
#     bash .devcontainer/update.sh --watch   # every 5 minutes (started by serve.sh)
cd "$(dirname "$0")/.."

update_now() {
    local branch before
    branch=$(git rev-parse --abbrev-ref HEAD 2> /dev/null) || return 0
    [ "$branch" = "HEAD" ] && return 0
    git fetch --quiet origin "$branch" 2> /dev/null || return 0
    [ "$(git rev-parse HEAD)" = "$(git rev-parse "origin/$branch")" ] && return 0

    before=$(git rev-parse HEAD)
    if ! git merge --ff-only --quiet "origin/$branch" > /dev/null 2>&1; then
        echo "[$(date +%H:%M)] Yangi versiya bor, lekin bu yerda qo'lda o'zgartirilgan fayllar sababli olinmadi (git status)."
        return 0
    fi

    # New libraries or a new installer: the whole installation is run again; otherwise the database only.
    if ! git diff --quiet "$before" HEAD -- composer.lock .devcontainer/setup.sh; then
        bash .devcontainer/setup.sh > /tmp/fair-value-update.log 2>&1 \
            || echo "O'rnatishda xato chiqdi: /tmp/fair-value-update.log"
    else
        php artisan migrate --force --no-interaction > /dev/null 2>&1
        php artisan optimize:clear > /dev/null 2>&1
    fi
    echo "[$(date +%H:%M)] Yangi versiya olindi ($(git rev-parse --short HEAD)): sahifani yangilang."
}

if [ "${1:-}" = "--watch" ]; then
    while true; do
        sleep "${UPDATE_EVERY:-300}"
        update_now
    done
else
    update_now
fi
