#!/bin/bash
# bin/docker-port-setup.sh
# Ensures .env exists with WORDPRESS_PORT and WORKTREE_ID

set -e

ENV_FILE=".env"
PORT_RANGE_START=8180
PORT_RANGE_END=8199

# Get default worktree ID from directory basename
DEFAULT_WORKTREE_ID=$(basename "$(pwd)" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g')

# Load existing .env if present
if [[ -f "$ENV_FILE" ]]; then
    source "$ENV_FILE"
fi

# Set WORKTREE_ID if not defined
if [[ -z "$WORKTREE_ID" ]]; then
    WORKTREE_ID="$DEFAULT_WORKTREE_ID"
    echo "WORKTREE_ID=$WORKTREE_ID" >> "$ENV_FILE"
    echo "Set WORKTREE_ID=$WORKTREE_ID"
fi

# Set WORDPRESS_PORT if not defined
if [[ -z "$WORDPRESS_PORT" ]]; then
    echo "Scanning for available port..."
    for port in $(seq $PORT_RANGE_START $PORT_RANGE_END); do
        if ! lsof -i ":$port" > /dev/null 2>&1; then
            WORDPRESS_PORT=$port
            break
        fi
    done

    if [[ -z "$WORDPRESS_PORT" ]]; then
        echo "Error: No available ports in range $PORT_RANGE_START-$PORT_RANGE_END"
        exit 1
    fi

    echo "WORDPRESS_PORT=$WORDPRESS_PORT" >> "$ENV_FILE"
    echo "Set WORDPRESS_PORT=$WORDPRESS_PORT"
fi

echo "Using WORKTREE_ID=$WORKTREE_ID, WORDPRESS_PORT=$WORDPRESS_PORT"
