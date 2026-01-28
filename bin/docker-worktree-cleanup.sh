#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources

set -e

if [[ ! -f ".env.local" ]]; then
    echo "No .env.local found - nothing to clean up"
    exit 0
fi

source .env.local

echo "Stopping containers for worktree: $WORKTREE_ID"
docker compose --env-file .env.local down

echo "Removing .env.local"
rm .env.local

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
