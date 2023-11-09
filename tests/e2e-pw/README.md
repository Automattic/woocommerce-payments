## Setup e2e environment

1. `npm run test:e2e-setup`
1. `npm run test:e2e-up`

## Run e2e tests with playwright

-   `npm run test:e2e-pw` (usual, headless run)
-   `npm run test:e2e-pw -- --headed` (headed -- displaying browser window and test interactions)
-   `npm run test:e2e-pw -- --ui` (runs tests in interactive UI mode)
-   `npm run test:e2e-pw -- --debug` (runs tests in debug mode)
-   `npm run test:e2e-pw -- --update-snapshots` (updates snapshots)
