#!/bin/bash
set -e

REPO_ROOT="$(git rev-parse --show-toplevel)"
JT_DIR="${REPO_ROOT}/bin/jurassictube"

if [ ! -f "${JT_DIR}/config.env" ]; then
    echo "Error: No jurassic tube config found. Is the tunnel running?"
    exit 1
fi

# shellcheck source=/dev/null
source "${JT_DIR}/config.env"

# Resolve port from .env
if [ -f "${REPO_ROOT}/.env" ]; then
    # shellcheck source=/dev/null
    source "${REPO_ROOT}/.env"
fi
PORT="${WORDPRESS_PORT:-8082}"

echo "Stopping tunnel: ${subdomain}.jurassic.tube"
jurassictube -b -s "$subdomain"

# Revert WordPress URLs to localhost
echo "Reverting WordPress URLs to http://localhost:${PORT} ..."
cd "$REPO_ROOT"
docker compose exec -u www-data wordpress wp option update siteurl "http://localhost:${PORT}" --quiet
docker compose exec -u www-data wordpress wp option update home "http://localhost:${PORT}" --quiet

echo "Done. Site reverted to http://localhost:${PORT}/"
