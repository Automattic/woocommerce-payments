#!/bin/bash

REPO_ROOT="$(git rev-parse --show-toplevel)"
MAIN_REPO="$(git worktree list --porcelain | head -1 | sed 's/worktree //')"
JT_DIR="${REPO_ROOT}/bin/jurassictube"
IS_WORKTREE=false

if [ "$REPO_ROOT" != "$MAIN_REPO" ]; then
    IS_WORKTREE=true
fi

if [ ! -f "${JT_DIR}/config.env" ]; then
    if [ "$IS_WORKTREE" = true ] && [ -f "${MAIN_REPO}/bin/jurassictube/config.env" ]; then
        echo "No local config (will copy from main repo on tube:start)"
        # shellcheck source=/dev/null
        source "${MAIN_REPO}/bin/jurassictube/config.env"
    else
        echo "Not configured. Run 'npm run tube:setup' first."
        exit 0
    fi
else
    # shellcheck source=/dev/null
    source "${JT_DIR}/config.env"
fi

# Resolve port
if [ -f "${REPO_ROOT}/.env" ]; then
    # shellcheck source=/dev/null
    source "${REPO_ROOT}/.env"
fi
PORT="${WORDPRESS_PORT:-8082}"

# Check if tunnel is active via the SSH ControlMaster socket.
# %h (hostname) and %p (port) are SSH ControlPath tokens, expanded by SSH at runtime.
# This path mirrors the socket pattern used by the jurassictube CLI.
SESSION_FILE="/tmp/jtube_session_%h_%p_${subdomain}"
TUNNEL_STATUS="inactive"
if ssh -S "$SESSION_FILE" -o ControlMaster=auto -O check jurassic.tube 2>/dev/null; then
    TUNNEL_STATUS="active"
fi

echo ""
echo "Jurassic Tube Status"
echo ""
echo "  Subdomain:  ${subdomain}.jurassic.tube"
echo "  Username:   ${username}"
echo "  Local port: ${PORT}"
echo "  Tunnel:     ${TUNNEL_STATUS}"
echo "  Worktree:   ${IS_WORKTREE}"
if [ "$IS_WORKTREE" = true ]; then
    echo "  Main repo:  ${MAIN_REPO}"
fi
echo ""
