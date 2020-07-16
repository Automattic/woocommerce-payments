#!/usr/bin/env bash
set -e

. ./tests/e2e/env/shared.sh

step "Removing deps"
rm -rf $E2E_ROOT/deps

step "Removing docker volumes"
rm -rf $E2E_ROOT/docker
