#!/bin/sh
# Shared dependency installer called by post-checkout, post-merge, and post-rewrite.
# Usage: install-deps.sh <from-ref> <to-ref>

# PHP dependencies first
# --ignore-platform-req=php allows dev-only packages (like qit-cli) that need PHP 7.4+
composer install --ignore-platform-req=php

# JS: warn if pnpm-lock.yaml changed
changed_files="$(git diff-tree -r --name-only --no-commit-id "$1" "$2" 2>/dev/null)"
if echo "$changed_files" | grep --quiet "pnpm-lock.yaml"; then
    printf '\n\033[1;33m*********************************************\n'
    printf '  ⚠  pnpm-lock.yaml changed!\n'
    printf '     Run: pnpm install\n'
    printf '*********************************************\033[0m\n\n'
fi

# JS: switch to the pnpm version pinned in package.json through corepack
pinned_pnpm="$(sed -nE 's/.*"packageManager": *"pnpm@([0-9.]+).*/\1/p' package.json)"
# no download prompt here; a hook can't answer it
current_pnpm="$(COREPACK_ENABLE_DOWNLOAD_PROMPT=0 pnpm --version 2>/dev/null)"
if [ -n "$pinned_pnpm" ] && [ "$current_pnpm" != "$pinned_pnpm" ]; then
    printf '\033[1;33mThe repo pins pnpm %s, but %s is in use.\033[0m\n' "$pinned_pnpm" "${current_pnpm:-no pnpm}"
    if command -v corepack > /dev/null; then
        printf 'Enabling corepack so pnpm switches to the pinned version.\n'
        corepack enable pnpm
    else
        printf 'Install pnpm %s, or a Node version that ships corepack.\n' "$pinned_pnpm"
    fi
fi
