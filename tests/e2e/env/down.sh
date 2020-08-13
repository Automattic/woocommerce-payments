#!/usr/bin/env bash
set -e

. ./tests/e2e/env/shared.sh

step "Stopping client containers"
docker-compose -f $E2E_ROOT/env/docker-compose.yml down

step "Stopping server containers"
docker-compose -f $E2E_ROOT/deps/wcp-server/docker-compose.yml down
