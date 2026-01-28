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
	retries: process.env.CI ? 1 : 0,

	/* Opt out of parallel tests - run one at a time */
	workers: 1,

	/* Reporter configuration for QIT */
	reporter: [
		[ 'list' ],
		[
			'playwright-ctrf-json-reporter',
			{
				outputDir: './test-results',
				outputFile: 'ctrf.json',
			},
		],
		[
			'json',
			{
				outputFile: './test-results/playwright-results.json',
			},
		],
		[
			'blob',
			{
				outputDir: './test-results/blob-report',
			},
		],
		[
			'html',
			{
				outputFolder: './playwright-report',
			},
		],
		[
			'allure-playwright',
			{
				resultsDir: './test-results/allure',
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
		{
			name: 'merchant',
			testDir: './tests/woopayments/merchant',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
		{
			name: 'subscriptions',
			testDir: './tests/woopayments/subscriptions',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
