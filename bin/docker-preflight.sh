#!/bin/bash
# bin/docker-preflight.sh
# Checks prerequisites before starting WordPress containers

set -e

# Check if the shared Docker network exists
if ! docker network inspect wcpay-network > /dev/null 2>&1; then
    echo "Error: The 'wcpay-network' Docker network does not exist."
    echo ""
    echo "Please start the shared infrastructure first by running:"
    echo "  npm run infra:up"
    echo ""
    echo "This only needs to be done once. It starts the shared database"
    echo "and phpMyAdmin containers that all worktrees connect to."
    exit 1
fi

# Check if the shared Docker volumes exist
for volume in wcpay-plugins wcpay-themes wcpay-uploads wcpay-mu-plugins; do
    if ! docker volume inspect "$volume" > /dev/null 2>&1; then
        echo "Error: The '$volume' Docker volume does not exist."
        echo ""
        echo "Please start the shared infrastructure first by running:"
        echo "  npm run infra:up"
        exit 1
    fi
done

# Ensure per-worktree log directories exist
mkdir -p docker/logs/wc-logs
mkdir -p docker/logs/apache2
