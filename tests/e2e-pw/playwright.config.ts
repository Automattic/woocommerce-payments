/* eslint-disable @typescript-eslint/naming-convention */
/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
// require('dotenv').config();

const { BASE_URL } = process.env;

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig( {
	testDir: './specs/',
	/* Run tests in files in parallel */
	fullyParallel: true,
	/* Fail the build on CI if you accidentally left test.only in the source code. */
	forbidOnly: !! process.env.CI,
	/* Retry on CI only */
	retries: process.env.CI ? 2 : 0,
	/* Opt out of parallel tests on CI. */
	workers: process.env.CI ? 1 : undefined,
	/* Reporter to use. See https://playwright.dev/docs/test-reporters */
	reporter: 'html',
	outputDir: './test-results',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8084',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
		// storageState: 'tests/e2e-pw/storage/state.json',
	},
	expect: {
		toHaveScreenshot: { maxDiffPixelRatio: 0.025 },
	},
	snapshotPathTemplate:
		'{testDir}/__snapshots__/{testFilePath}/{arg}_{projectName}{ext}',
	// globalSetup: require.resolve( './global-setup' ),
	// globalTeardown: require.resolve( './global-teardown' ),

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},

		// Setup project
		{ name: 'setup', testMatch: /.*\.setup\.ts/ },
		// {
		// 	name: 'firefox',
		// 	use: { ...devices[ 'Desktop Firefox' ] },
		// },

		// {
		// 	name: 'webkit',
		// 	use: { ...devices[ 'Desktop Safari' ] },
		// },

		/* Test against mobile viewports. */
		// {
		//   name: 'Mobile Chrome',
		//   use: { ...devices['Pixel 5'] },
		// },
		// {
		//   name: 'Mobile Safari',
		//   use: { ...devices['iPhone 12'] },
		// },

		/* Test against branded browsers. */
		// {
		//   name: 'Microsoft Edge',
		//   use: { ...devices['Desktop Edge'], channel: 'msedge' },
		// },
		// {
		//   name: 'Google Chrome',
		//   use: { ...devices['Desktop Chrome'], channel: 'chrome' },
		// },
	],
} );
