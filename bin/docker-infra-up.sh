#!/bin/bash
# bin/docker-infra-up.sh
# Starts shared infrastructure (database, phpMyAdmin) and creates shared volumes

set -e

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

echo "Using shared WordPress path: $WCPAY_SHARED_WP_PATH"

# Ensure the wp-content directories exist (required for bind mounts)
echo "Ensuring wp-content directories exist..."
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/plugins"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/themes"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/uploads"
mkdir -p "$WCPAY_SHARED_WP_PATH/wp-content/mu-plugins"

echo "Starting shared infrastructure..."
docker compose -f docker-compose.infra.yml up -d

echo ""
echo "Shared infrastructure is running:"
echo "  - Database: wcpay_db (localhost:5678)"
echo "  - phpMyAdmin: http://localhost:8083"
echo ""
echo "Shared volumes bound to: $WCPAY_SHARED_WP_PATH/wp-content/"
docker volume ls --filter name=wcpay- --format "  - {{.Name}}"
