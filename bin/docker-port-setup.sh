#!/bin/bash
# bin/docker-port-setup.sh
# Ensures .env exists with WORDPRESS_PORT, MYSQL_PORT, PHPMYADMIN_PORT,
# WORKTREE_ID, and COMPOSE_PROJECT_NAME set to unique values for this checkout.
#
# Usage:
#   npm run worktree:setup        # Interactive setup
#   ./bin/docker-port-setup.sh    # Direct invocation

set -e

ENV_FILE=".env"
DEFAULT_WORDPRESS_PORT=8082
DEFAULT_MYSQL_PORT=5678
DEFAULT_PHPMYADMIN_PORT=8083
# Range for additional checkouts / worktrees
WP_PORT_RANGE_START=8180
WP_PORT_RANGE_END=8199
MYSQL_PORT_RANGE_START=5679
MYSQL_PORT_RANGE_END=5699
PMA_PORT_RANGE_START=8200
PMA_PORT_RANGE_END=8219
CURRENT_DIR="$(pwd)"

# Generate a sanitized identifier from a string (lowercase alphanumeric + underscores)
sanitize_id() {
    echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g' | sed 's/__*/_/g' | sed 's/^_//;s/_$//'
}

# Collect all ports already reserved by sibling worktrees in their .env files
get_sibling_reserved_ports() {
    git worktree list 2>/dev/null | awk '{print $1}' | while read -r dir; do
        [[ "$dir" == "$CURRENT_DIR" ]] && continue
        if [[ -f "$dir/.env" ]]; then
            grep -E '^(WORDPRESS_PORT|MYSQL_PORT|PHPMYADMIN_PORT)=' "$dir/.env" 2>/dev/null | cut -d= -f2
        fi
    done
}

# Check whether a TCP port is currently bound on the host
is_port_in_use() {
    local port=$1
    if command -v ss &>/dev/null; then
        ss -tln 2>/dev/null | grep -q ":${port} \|:${port}$" && return 0
    elif command -v lsof &>/dev/null; then
        lsof -ti ":${port}" &>/dev/null && return 0
    fi
    return 1
}

# Return 0 (true) if the port is neither in use nor reserved by a sibling
is_port_available() {
    local port=$1
    echo "$SIBLING_PORTS" | grep -q "^${port}$" && return 1
    is_port_in_use "$port" && return 1
    return 0
}

# Find the first available port in [start, end]
find_available_port() {
    local start=$1
    local end=$2
    for ((port = start; port <= end; port++)); do
        if is_port_available "$port"; then
            echo "$port"
            return 0
        fi
    done
    return 1
}

# ---------------------------------------------------------------------------
# Determine whether we are in a git worktree (not the main checkout)
# ---------------------------------------------------------------------------
GIT_DIR=$(git rev-parse --git-dir 2>/dev/null || echo "")
GIT_COMMON_DIR=$(git rev-parse --git-common-dir 2>/dev/null || echo "")
IS_WORKTREE=false
if [[ -n "$GIT_DIR" && "$GIT_DIR" != "$GIT_COMMON_DIR" && "$GIT_DIR" != ".git" ]]; then
    IS_WORKTREE=true
fi

# ---------------------------------------------------------------------------
# Load existing .env so we can preserve values the user may have set manually
# ---------------------------------------------------------------------------
if [[ -f "$ENV_FILE" ]]; then
    # shellcheck source=/dev/null
    source "$ENV_FILE"
fi

# ---------------------------------------------------------------------------
# Gather ports already in use by sibling worktrees
# ---------------------------------------------------------------------------
SIBLING_PORTS=$(get_sibling_reserved_ports)

# ---------------------------------------------------------------------------
# WORKTREE_ID — derive from directory basename if not already set
# ---------------------------------------------------------------------------
if [[ -z "$WORKTREE_ID" ]]; then
    DIR_NAME=$(basename "$CURRENT_DIR")
    WORKTREE_ID=$(sanitize_id "$DIR_NAME")
    [[ -z "$WORKTREE_ID" ]] && WORKTREE_ID="default"
fi

# ---------------------------------------------------------------------------
# COMPOSE_PROJECT_NAME — used by Docker Compose to namespace containers,
# networks, and volumes so multiple checkouts don't collide
# ---------------------------------------------------------------------------
if [[ -z "$COMPOSE_PROJECT_NAME" ]]; then
    COMPOSE_PROJECT_NAME="wcpay_${WORKTREE_ID}"
fi

# ---------------------------------------------------------------------------
# Port allocation
# For the main checkout we try the historic defaults first.
# For worktrees / additional checkouts we always scan the dedicated range.
# ---------------------------------------------------------------------------
if [[ -z "$WORDPRESS_PORT" ]]; then
    if [[ "$IS_WORKTREE" == "false" ]] \
        && is_port_available "$DEFAULT_WORDPRESS_PORT" \
        && is_port_available "$DEFAULT_MYSQL_PORT" \
        && is_port_available "$DEFAULT_PHPMYADMIN_PORT"; then
        WORDPRESS_PORT=$DEFAULT_WORDPRESS_PORT
        MYSQL_PORT=$DEFAULT_MYSQL_PORT
        PHPMYADMIN_PORT=$DEFAULT_PHPMYADMIN_PORT
    else
        WORDPRESS_PORT=$(find_available_port "$WP_PORT_RANGE_START" "$WP_PORT_RANGE_END") || {
            echo "ERROR: No available WordPress port found in range ${WP_PORT_RANGE_START}-${WP_PORT_RANGE_END}." >&2
            echo "Set WORDPRESS_PORT manually in .env and re-run." >&2
            exit 1
        }
        MYSQL_PORT=$(find_available_port "$MYSQL_PORT_RANGE_START" "$MYSQL_PORT_RANGE_END") || {
            echo "ERROR: No available MySQL port found in range ${MYSQL_PORT_RANGE_START}-${MYSQL_PORT_RANGE_END}." >&2
            echo "Set MYSQL_PORT manually in .env and re-run." >&2
            exit 1
        }
        PHPMYADMIN_PORT=$(find_available_port "$PMA_PORT_RANGE_START" "$PMA_PORT_RANGE_END") || {
            echo "ERROR: No available phpMyAdmin port found in range ${PMA_PORT_RANGE_START}-${PMA_PORT_RANGE_END}." >&2
            echo "Set PHPMYADMIN_PORT manually in .env and re-run." >&2
            exit 1
        }
    fi
fi

# ---------------------------------------------------------------------------
# Write (or update) .env
# ---------------------------------------------------------------------------
cat > "$ENV_FILE" <<EOF
WORKTREE_ID=${WORKTREE_ID}
COMPOSE_PROJECT_NAME=${COMPOSE_PROJECT_NAME}
WORDPRESS_PORT=${WORDPRESS_PORT}
MYSQL_PORT=${MYSQL_PORT:-$DEFAULT_MYSQL_PORT}
PHPMYADMIN_PORT=${PHPMYADMIN_PORT:-$DEFAULT_PHPMYADMIN_PORT}
EOF

echo "Environment configured in .env:"
echo "  WORKTREE_ID:          ${WORKTREE_ID}"
echo "  COMPOSE_PROJECT_NAME: ${COMPOSE_PROJECT_NAME}"
echo "  WORDPRESS_PORT:       ${WORDPRESS_PORT}  →  http://localhost:${WORDPRESS_PORT}"
echo "  MYSQL_PORT:           ${MYSQL_PORT:-$DEFAULT_MYSQL_PORT}"
echo "  PHPMYADMIN_PORT:      ${PHPMYADMIN_PORT:-$DEFAULT_PHPMYADMIN_PORT}  →  http://localhost:${PHPMYADMIN_PORT:-$DEFAULT_PHPMYADMIN_PORT}"
