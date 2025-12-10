/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

/**
 * Playwright configuration for WooCommerce Payments QIT E2E tests
 *
 * @see https://playwright.dev/docs/test-configuration
 */
export default defineConfig( {
	testDir: './tests',

	/* Run tests sequentially for stability */
	fullyParallel: false,

	/* Fail the build on CI if you accidentally left test.only in the source code */
	forbidOnly: !! process.env.CI,

	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,

	/* Opt out of parallel tests - run one at a time */
	workers: 1,

	/* Reporter configuration for QIT */
	reporter: [
		[ 'list' ],
		[ 'html', { open: 'never', outputFolder: './results/html' } ],
		[
			'playwright-ctrf-json-reporter',
			{
				outputDir: './results',
				outputFile: 'ctrf.json',
			},
		],
		[
			'allure-playwright',
			{
				resultsDir: './results/allure',
			},
		],
		[
			'blob',
			{
				outputDir: './results/blob',
			},
		],
	],

	/* Shared settings for all projects */
	use: {
		/* Base URL from QIT environment */
		baseURL: process.env.QIT_SITE_URL || 'http://localhost:8080',

		/* Collect trace/screenshots/video only on failure */
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		trace: 'retain-on-failure',

		/* Browser viewport */
		viewport: { width: 1280, height: 720 },
	},

	/* Test timeout */
	timeout: 120 * 1000, // 2 minutes per test

	/* Expect timeout */
	expect: {
		timeout: 20 * 1000, // 20 seconds for assertions
	},

	/* Configure projects for subpackages */
	projects: [
		{
			name: 'default',
			use: { ...devices[ 'Desktop Chrome' ] },
			testMatch: /basic\.spec\.ts$/,
		},
		{
			name: 'shopper',
			testDir: './tests/woopayments/shopper',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		// Additional projects for merchant and subscriptions subpackages
		// will be added when those tests are migrated.
	],
} );
