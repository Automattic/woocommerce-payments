#!/bin/bash

echo "🎭 Running Playwright e2e tests in default headless mode.";

docker compose -f ./tests/e2e-pw/docker-compose.yml run --rm -it --service-ports playwright \
	npx playwright test --config=tests/e2e-pw/playwright.config.ts "$@"
