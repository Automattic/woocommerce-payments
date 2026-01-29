#!/bin/bash
# bin/docker-infra-up.sh
# Starts shared infrastructure (database, phpMyAdmin) and creates shared volumes

set -e

echo "Creating shared Docker volumes..."
for volume in wcpay-plugins wcpay-themes wcpay-uploads wcpay-mu-plugins; do
    if ! docker volume inspect "$volume" > /dev/null 2>&1; then
        docker volume create "$volume"
        echo "  Created: $volume"
    else
        echo "  Exists: $volume"
    fi
done

echo "Starting shared infrastructure..."
docker compose -f docker-compose.infra.yml up -d

echo ""
echo "Shared infrastructure is running:"
echo "  - Database: wcpay_db (localhost:5678)"
echo "  - phpMyAdmin: http://localhost:8083"
echo ""
echo "Shared volumes ready:"
docker volume ls --filter name=wcpay- --format "  - {{.Name}}"
