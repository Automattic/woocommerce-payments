#!/bin/bash
# bin/docker-preflight.sh
# Checks prerequisites before starting WordPress containers
# Auto-starts shared infrastructure if not running

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Check if infrastructure needs to be started
infra_needed=false

if ! docker network inspect wcpay-network > /dev/null 2>&1; then
    infra_needed=true
fi

for volume in wcpay-plugins wcpay-themes wcpay-uploads wcpay-mu-plugins; do
    if ! docker volume inspect "$volume" > /dev/null 2>&1; then
        infra_needed=true
        break
    fi
done

# Auto-start infrastructure if needed
if [[ "$infra_needed" == "true" ]]; then
    echo "Starting shared infrastructure (database, phpMyAdmin)..."
    bash "$SCRIPT_DIR/docker-infra-up.sh"
fi

# Ensure per-worktree log directories exist
mkdir -p docker/logs/wc-logs
mkdir -p docker/logs/apache2
