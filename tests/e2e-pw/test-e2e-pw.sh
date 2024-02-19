#!/bin/bash

echo "🎭 Running Playwright e2e tests in default headless mode.";

# When updating the Playwright version in the image tage below, make sure to update the npm `@playwright/test` package.json version as well.

docker run -v "$PWD:/woopayments" -w /woopayments \
	-e BASE_URL=http://host.docker.internal:8084 \
	-it --rm --ipc=host \
	mcr.microsoft.com/playwright:v1.39.0-jammy \
	npx playwright test --config=tests/e2e-pw/playwright.config.ts "$@"
