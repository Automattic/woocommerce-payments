#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources

set -e

if [[ ! -f ".env" ]]; then
    echo "No .env file detected, which means you might not be on a worktree."
    read -p "Do you want to continue? (y/N) " -r
    # Only continue if user explicitly confirms with: y or yes (case-insensitive)
    if [[ ! ${REPLY,,} =~ ^y(es)?$ ]]; then
        exit 0
    fi
fi

echo "Stopping containers for this worktree"
npm run down

if [ -f ".env" ]; then
	echo "Removing .env"
    rm .env
fi

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
