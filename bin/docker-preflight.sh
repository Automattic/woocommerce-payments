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
