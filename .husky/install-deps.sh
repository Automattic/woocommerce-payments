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
