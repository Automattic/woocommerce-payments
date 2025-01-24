#!/usr/bin/env bash

echo "🎭 Running Playwright e2e tests in default headless mode, skipping @todo.";

docker compose -f ./tests/e2e-pw/docker-compose.yml run --rm -it --service-ports playwright \
	npx playwright test --only-changed --pass-with-no-tests --config=tests/e2e-pw/playwright.config.ts --grep-invert @todo "$@"
