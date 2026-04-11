#!/usr/bin/env bash

echo "🎭 Running Playwright e2e performance tests in default headless mode, skipping @todo.";

docker compose -f ./tests/e2e/docker-compose.yml run --rm -it --service-ports playwright \
	npx playwright test --config=tests/e2e/playwright.performance.config.ts --grep-invert @todo "$@"
