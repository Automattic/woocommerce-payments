/**
 * External dependencies
 */
import { defineConfig, devices } from '@playwright/test';

export default defineConfig( {
	testDir: './specs',
	fullyParallel: false,
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 2 : 0,
	workers: 1,
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
	use: {
		baseURL: process.env.QIT_SITE_URL || 'http://localhost:8080',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
		trace: 'retain-on-failure',
		viewport: { width: 1280, height: 720 },
	},
	timeout: 120 * 1000,
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
