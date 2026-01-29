#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources

set -e

if [[ ! -f ".env" ]]; then
    echo "No .env found - nothing to clean up"
    exit 0
fi

source .env

echo "Stopping containers for worktree: $WORKTREE_ID"
docker compose --env-file .env down

echo "Removing .env"
rm .env

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
