/* eslint-disable @typescript-eslint/naming-convention */
/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
import { config } from 'dotenv';
import path from 'path';

config( { path: path.resolve( __dirname, '../e2e/config', '.env' ) } );
config( { path: path.resolve( __dirname, '../e2e/config', 'local.env' ) } );

const { BASE_URL, E2E_GROUP, E2E_BRANCH, E2E_RESULT_FILEPATH } = process.env;

const validGroups = [ 'wcpay', 'subscriptions' ];
const validBranches = [ 'merchant', 'shopper' ];

const buildTestDir = ( group: string, branch: string ) => {
	const baseDir = `\/specs`;

	if ( ! group || ! validGroups.includes( group ) ) {
		return baseDir;
	}

	if ( ! branch || ! validBranches.includes( branch ) ) {
		return `${ baseDir }\/${ group }`;
	}

	return `${ baseDir }\/${ group }\/${ branch }`;
};

const getTestMatch = ( group: string, branch: string ) => {
	const testDir = buildTestDir( group, branch );

	return new RegExp( `${ testDir }\/.*\.spec\.ts` );
};

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig( {
	testDir: './specs/',
	/* Run tests in files in parallel */
	fullyParallel: false,
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
				[ 'json', { outputFile: E2E_RESULT_FILEPATH } ],
				[ 'html' ],
		  ]
		: [ [ 'html', { open: 'never' } ] ],
	outputDir: './test-results',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		baseURL: BASE_URL ?? 'http://localhost:8084',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},
	timeout: 120 * 1000, // Default is 30s, somteimes it is not enough for local tests due to long setup.
	expect: {
		toHaveScreenshot: { maxDiffPixelRatio: 0.025 },
		//=* Increase expect timeout to 10 seconds. See https://playwright.dev/docs/test-timeouts#set-expect-timeout-in-the-config.*/
		timeout: 20 * 1000,
	},
	snapshotPathTemplate: '{testDir}/__snapshots__/{testFilePath}/{arg}{ext}',

	testMatch: getTestMatch( E2E_GROUP, E2E_BRANCH ),

	/* Configure projects for major browsers */
	projects: [
		{
			name: 'basic',
			use: { ...devices[ 'Desktop Chrome' ] },
			testMatch: /basic.spec.ts/,
			dependencies: [ 'setup' ],
		},
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
		},
		// Setup project
		{ name: 'setup', testMatch: /.*\.setup\.ts/ },
	],
} );
