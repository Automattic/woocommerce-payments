# QIT E2E Tests in CI

This document explains how the QIT E2E tests are integrated into GitHub Actions CI/CD pipeline.

## Workflow Overview

The QIT E2E tests run automatically in GitHub Actions through the `.github/workflows/qit-e2e.yml` workflow.

### When Tests Run

- **Pull Requests**: On PRs that modify relevant files (client, includes, src, QIT test files)
- **Push to main branches**: develop, trunk, and QIT development branches
- **Scheduled**: Daily at 2 AM UTC
- **Manual**: Via workflow dispatch with custom parameters

### Test Matrix

The workflow tests against multiple combinations:

- **WooCommerce versions**: stable, L-1 (8.9.3), and business continuity (7.7.0)
- **PHP versions**: 8.3, 8.2, 8.1, 7.4
- **Test groups**: Currently focuses on basic connectivity tests

### Required Secrets

The workflow requires these GitHub secrets to be configured:

- `QIT_CI_USER`: QIT partner username
- `QIT_CI_SECRET`: QIT application password
- `E2E_JP_SITE_ID`: Jetpack site ID for test account connection
- `E2E_JP_BLOG_TOKEN`: Jetpack blog token
- `E2E_JP_USER_TOKEN`: Jetpack user token

### Manual Workflow Dispatch

You can manually trigger the workflow with custom parameters:

1. Go to Actions → QIT E2E Tests
2. Click "Run workflow"
3. Configure:
   - **WooCommerce version**: Choose version to test against
   - **PHP version**: Select PHP version
   - **UI mode**: Enable for debugging (shows test execution in browser)

### How It Works

1. **Build**: The workflow builds the plugin using the standard build process
2. **QIT Setup**: Installs QIT CLI and authenticates with partner credentials
3. **Test Execution**: Runs `npm run test:qit-e2e` which uses our `e2e-runner.sh`
4. **Results**: Test results are uploaded as artifacts and managed by QIT's reporting system

### Local vs CI Differences

- **Authentication**: CI uses partner credentials, local can use test credentials
- **Environment**: CI runs in isolated Docker containers managed by QIT
- **Reporting**: CI results are available through QIT's centralized reporting

### Debugging Failed Tests

1. Check the workflow run logs in GitHub Actions
2. Download test artifacts if available
3. Use manual workflow dispatch with UI mode enabled for visual debugging
4. Use `qit e2e-report` command locally with partner credentials to view detailed reports

### Environment Variables

The workflow passes these environment variables to the test runner:

- `E2E_JP_SITE_ID`, `E2E_JP_BLOG_TOKEN`, `E2E_JP_USER_TOKEN`: Jetpack connection credentials
- `QIT_OPTIONS`: Additional QIT CLI options (PHP version, WooCommerce version, UI mode)

These are automatically handled by the `e2e-runner.sh` script.