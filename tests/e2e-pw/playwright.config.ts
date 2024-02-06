/* eslint-disable @typescript-eslint/naming-convention */
/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

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
	/* Opt out of parallel tests. */
	workers: 1,
	/* Reporters to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
				// If running on CI, also use the GitHub Actions reporter
				[ 'github' ],
				[ 'html' ],
		  ]
		: [ [ 'html' ] ],
	outputDir: './test-results',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8084',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},
	expect: {
		toHaveScreenshot: { maxDiffPixelRatio: 0.025 },
	},
	snapshotPathTemplate:
		'{testDir}/__snapshots__/{testFilePath}/{arg}_{projectName}{ext}',

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},

		// Setup project
		{ name: 'setup', testMatch: /.*\.setup\.ts/ },
	],
} );
