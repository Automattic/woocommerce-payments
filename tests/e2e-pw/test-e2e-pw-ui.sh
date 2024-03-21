#!/usr/bin/env bash

echo "🎭 Running Playwright e2e tests in interactive UI mode.";
echo "";
echo "Open http://localhost:8077 in your browser to see the UI.";

docker compose -f ./tests/e2e-pw/docker-compose.yml run --rm -it --service-ports playwright \
	npx playwright test --config=tests/e2e-pw/playwright.config.ts --ui --ui-host=0.0.0.0 --ui-port=8077 "$@"
