/* eslint-disable @typescript-eslint/naming-convention */
/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';
import { config } from 'dotenv';
import path from 'path';

config( { path: path.resolve( __dirname, '../e2e/config', '.env' ) } );
config( { path: path.resolve( __dirname, '../e2e/config', 'local.env' ) } );

const { BASE_URL, NODE_ENV, E2E_GROUP, E2E_BRANCH } = process.env;

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

const getBaseUrl = () => {
	if ( NODE_ENV === 'atomic' ) {
		return 'https://wcpaytestecomm.wpcomstaging.com/';
	}

	return BASE_URL ?? 'http://localhost:8084';
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
	retries: 0,
	/* Opt out of parallel tests. */
	workers: 1,
	/* Reporters to use. See https://playwright.dev/docs/test-reporters */
	reporter: process.env.CI
		? [
				/* If running on CI, include the dot reporter and JSON reporter. */
				[ 'dot' ],
				[ 'json', { outputFile: 'results.json' } ],
				[ 'html' ],
				[ './reporters/slack-reporter.ts' ],
		  ]
		: [ [ 'html', { open: 'never' } ] ],
	outputDir: './test-results',
	/* Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions. */
	use: {
		baseURL: getBaseUrl(),
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		video: 'on-first-retry',
		viewport: { width: 1280, height: 720 },
	},
	timeout: 120 * 1000, // Default is 30s, sometimes it is not enough for local tests due to long setup.
	expect: {
		toHaveScreenshot: {
			maxDiffPixelRatio:
				process.env.E2E_WC_VERSION === '7.7.0' ? 0.035 : 0.025,
		},
		//=* Increase expect timeout to 10 seconds. See https://playwright.dev/docs/test-timeouts#set-expect-timeout-in-the-config.*/
		timeout: 20 * 1000,
	},
	snapshotPathTemplate: '{testDir}/__snapshots__/{testFilePath}/{arg}{ext}',

	testMatch: getTestMatch( E2E_GROUP, E2E_BRANCH ),
	testIgnore: /specs\/performance/,

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
