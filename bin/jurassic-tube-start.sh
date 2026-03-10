#!/bin/bash
set -e

REPO_ROOT="$(git rev-parse --show-toplevel)"
MAIN_REPO="$(git worktree list --porcelain | head -1 | sed 's/worktree //')"
JT_DIR="${REPO_ROOT}/bin/jurassictube"
IS_WORKTREE=false

if [ "$REPO_ROOT" != "$MAIN_REPO" ]; then
    IS_WORKTREE=true
fi

# --- Resolve config ---

# If no local config, try to copy from main repo (worktree scenario)
if [ ! -f "${JT_DIR}/config.env" ]; then
    if [ "$IS_WORKTREE" = false ]; then
        echo "Error: No jurassic tube config found. Run 'npm run tube:setup' first."
        exit 1
    fi

    MAIN_JT="${MAIN_REPO}/bin/jurassictube"
    if [ ! -f "${MAIN_JT}/config.env" ]; then
        echo "Error: No jurassic tube config in main repo. Run 'npm run tube:setup' there first."
        exit 1
    fi

    echo "Copying jurassic tube config from main repo..."
    mkdir -p "${JT_DIR}"
    cp "${MAIN_JT}/config.env" "${JT_DIR}/config.env"
    # Copy keys and scripts (these are user-specific, safe to share)
    for f in key key.pub installer.sh jurassictube.sh update.sh; do
        cp "${MAIN_JT}/${f}" "${JT_DIR}/${f}" 2>/dev/null || true
    done
    chmod 600 "${JT_DIR}/key" 2>/dev/null || true
fi

# shellcheck source=/dev/null
source "${JT_DIR}/config.env"

# --- Resolve port ---
# Port from .env takes precedence (worktree-aware)
if [ -f "${REPO_ROOT}/.env" ]; then
    # shellcheck source=/dev/null
    source "${REPO_ROOT}/.env"
fi
PORT="${WORDPRESS_PORT:-8082}"
HOST="localhost:${PORT}"

echo "Starting tunnel: ${subdomain}.jurassic.tube → ${HOST}"

# --- Start tunnel ---
# WordPress URLs are handled automatically by wp-config.php (DOCKER_HOST derives from HTTP_HOST),
# so no DB updates are needed — the tunnel's subdomain is picked up from the request headers.
jurassictube -u "$username" -s "$subdomain" -h "$HOST"

echo ""
echo "Tunnel ready: https://${subdomain}.jurassic.tube/"
echo "To stop: npm run tube:stop"
