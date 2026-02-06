#!/bin/bash
# bin/docker-infra-up.sh
# Starts shared infrastructure (database, phpMyAdmin) and creates shared volumes

set -e

# Warn if running from a worktree (not main checkout)
if git rev-parse --is-inside-work-tree &>/dev/null; then
    GIT_DIR=$(git rev-parse --git-dir)
    GIT_COMMON_DIR=$(git rev-parse --git-common-dir)
    if [[ "$GIT_DIR" != "$GIT_COMMON_DIR" ]]; then
        echo ""
        echo "WARNING: Running infra:up from a worktree."
        echo "   If this is not intended, run infra:up from your main checkout instead."
        echo ""
        read -p "Do you want to continue? (y/N) " -r
        if [[ ! ${REPLY,,} =~ ^y(es)?$ ]]; then
            echo "Aborted."
            exit 0
        fi
    fi
fi

# Load default env for WCPAY_SHARED_WP_PATH
source default.env

# Allow override from local .env
if [[ -f ".env" ]]; then
    source .env
fi

# Resolve WCPAY_SHARED_WP_PATH to absolute path
if [[ "${WCPAY_SHARED_WP_PATH:0:1}" != "/" ]]; then
    WCPAY_SHARED_WP_PATH="$(cd "$(dirname "$WCPAY_SHARED_WP_PATH")" 2>/dev/null && pwd)/$(basename "$WCPAY_SHARED_WP_PATH")"
fi
export WCPAY_SHARED_WP_PATH

# Resolve WCPAY_DB_DATA_PATH to absolute path
if [[ "${WCPAY_DB_DATA_PATH:0:1}" != "/" ]]; then
    WCPAY_DB_DATA_PATH="$(cd "$(dirname "$WCPAY_DB_DATA_PATH")" 2>/dev/null && pwd)/$(basename "$WCPAY_DB_DATA_PATH")"
fi
export WCPAY_DB_DATA_PATH

echo "Using shared WordPress path: $WCPAY_SHARED_WP_PATH"
echo "Using database data path: $WCPAY_DB_DATA_PATH"

# Ensure the wp-content directories exist (required for bind mounts)
echo "Ensuring wp-content directories exist..."
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/plugins"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/themes"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/uploads"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/mu-plugins"

# Ensure database data directory exists
mkdir -p "$WCPAY_DB_DATA_PATH"

echo "Starting shared infrastructure..."
docker compose -f docker-compose.infra.yml up -d

echo ""
echo "Shared infrastructure is running:"
echo "  - Database: wcpay_db (localhost:5678)"
echo "  - phpMyAdmin: http://localhost:8083"
echo ""
echo "Shared volumes bound to: $WCPAY_SHARED_WP_PATH/wp-content/"
docker volume ls --filter name=wcpay- --format "  - {{.Name}}"
