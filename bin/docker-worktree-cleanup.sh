#!/bin/bash
# bin/docker-worktree-cleanup.sh
# Run this before `git worktree remove` to clean up Docker resources

set -e

# Load worktree config if available
WORKTREE_ID="default"
if [[ -f ".env" ]]; then
    source .env
else
    echo "No .env file detected, which means you might not be on a worktree."
    read -p "Do you want to continue? (y/N) " -r
    # Only continue if user explicitly confirms with: y or yes (case-insensitive)
    if [[ ! ${REPLY,,} =~ ^y(es)?$ ]]; then
        exit 0
    fi
fi

echo "Stopping containers for this worktree"
npm run down

# Drop test database if it exists
TEST_DB_NAME="wcpay_tests_${WORKTREE_ID}"
if docker ps --format '{{.Names}}' | grep -q "wcpay_db"; then
    echo "Checking for test database: ${TEST_DB_NAME}"
    # Check if database exists before attempting to drop
    DB_EXISTS=$(docker exec wcpay_db mysql -uroot -pwordpress -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '${TEST_DB_NAME}';" 2>/dev/null | grep -c "${TEST_DB_NAME}" || true)
    if [[ "$DB_EXISTS" -gt 0 ]]; then
        echo "Dropping test database: ${TEST_DB_NAME}"
        docker exec wcpay_db mysql -uroot -pwordpress -e "DROP DATABASE IF EXISTS \`${TEST_DB_NAME}\`;" 2>/dev/null
        echo "Test database dropped."
    else
        echo "No test database found for this worktree."
    fi
else
    echo "Database container not running, skipping test database cleanup."
fi

if [[ -f ".env" ]]; then
    echo "Removing .env"
    rm .env
fi

echo "Cleanup complete. You can now run: git worktree remove $(pwd)"
