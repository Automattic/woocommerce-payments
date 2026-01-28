#!/bin/bash
# bin/docker-port-setup.sh
# Ensures .env.local exists with WP_PORT and WORKTREE_ID

set -e

ENV_FILE=".env.local"
PORT_RANGE_START=8082
PORT_RANGE_END=8099

# Get default worktree ID from directory basename
DEFAULT_WORKTREE_ID=$(basename "$(pwd)" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g')

# Load existing .env.local if present
if [[ -f "$ENV_FILE" ]]; then
    source "$ENV_FILE"
fi

# Set WORKTREE_ID if not defined
if [[ -z "$WORKTREE_ID" ]]; then
    WORKTREE_ID="$DEFAULT_WORKTREE_ID"
    echo "WORKTREE_ID=$WORKTREE_ID" >> "$ENV_FILE"
    echo "Set WORKTREE_ID=$WORKTREE_ID"
fi

# Set WP_PORT if not defined
if [[ -z "$WP_PORT" ]]; then
    echo "Scanning for available port..."
    for port in $(seq $PORT_RANGE_START $PORT_RANGE_END); do
        if ! lsof -i ":$port" > /dev/null 2>&1; then
            WP_PORT=$port
            break
        fi
    done

    if [[ -z "$WP_PORT" ]]; then
        echo "Error: No available ports in range $PORT_RANGE_START-$PORT_RANGE_END"
        exit 1
    fi

    echo "WP_PORT=$WP_PORT" >> "$ENV_FILE"
    echo "Set WP_PORT=$WP_PORT"
fi

echo "Using WORKTREE_ID=$WORKTREE_ID, WP_PORT=$WP_PORT"
