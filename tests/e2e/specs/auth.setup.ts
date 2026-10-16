/* eslint-disable no-console */
/**
 * External dependencies
 */
import { test as setup, expect } from '@playwright/test';
import fs from 'fs';

/**
 * Internal dependencies
 */
import { config } from '../config/default';
import {
	merchantStorageFile,
	customerStorageFile,
	editorStorageFile,
	wpAdminLogin,
	loginAsCustomer,
	loginAsEditor,
	addSupportSessionDetectedCookie,
} from '../utils/helpers';

// See https://playwright.dev/docs/auth#multiple-signed-in-roles
const {
	users: { admin, customer, editor },
} = config;

const isAuthStateStale = ( authStateFile: string ) => {
	const authFileExists = fs.existsSync( authStateFile );

	if ( ! authFileExists ) {
		return true;
	}

	const authStateMtimeMs = fs.statSync( authStateFile ).mtimeMs;
	const hourInMs = 1000 * 60 * 60;
	// Invalidate auth state if it's older than a 3 hours.
	const isStale = Date.now() - authStateMtimeMs > hourInMs * 3;
	return isStale;
};

setup( 'authenticate as admin', async ( { page }, testInfo ) => {
	const { project } = testInfo;

	// For local development, use existing state if it exists and isn't stale.
	if ( ! process.env.CI ) {
		if ( ! isAuthStateStale( merchantStorageFile ) ) {
			console.log( 'Using existing merchant state.' );
			return;
		}
	}

	await addSupportSessionDetectedCookie( page, project );

	// Sign in as admin user and save state. We allow one retry for transient
	// login failures, but every retry is annotated on the test report so the
	// release lead can spot flake during the Thursday Week 4 manual E2E run
	// (release process: silent retries actively hide that signal).
	const adminAttempts = 2;
	let lastError: unknown;
	for ( let i = 1; i <= adminAttempts; i++ ) {
		try {
			console.log(
				`Trying to log-in as admin (attempt ${ i }/${ adminAttempts })...`
			);
			await wpAdminLogin( page, admin );
			await page.waitForLoadState( 'domcontentloaded' );
			await page.goto( `/wp-admin` );
			await page.waitForLoadState( 'domcontentloaded' );

			await expect(
				page.getByRole( 'heading', { name: 'Dashboard' } )
			).toBeVisible();

			if ( i > 1 ) {
				testInfo.annotations.push( {
					type: 'flake',
					description: `Admin login required ${ i } attempts. Investigate before assuming flake.`,
				} );
			}

			await page.context().storageState( { path: merchantStorageFile } );
			return;
		} catch ( e ) {
			lastError = e;
			console.error(
				`Admin log-in attempt ${ i }/${ adminAttempts } failed.`,
				e
			);
		}
	}

	throw new Error(
		`Cannot proceed e2e test: admin login failed after ${ adminAttempts } attempts. Last error: ${ String(
			lastError
		) }`
	);
} );

setup( 'authenticate as customer', async ( { page }, { project } ) => {
	// For local development, use existing state if it exists and isn't stale.
	if ( ! process.env.CI ) {
		if ( ! isAuthStateStale( customerStorageFile ) ) {
			console.log( 'Using existing customer state.' );
			return;
		}
	}

	await addSupportSessionDetectedCookie( page, project );
	await loginAsCustomer( page, customer );
} );

setup( 'authenticate as editor', async ( { page }, { project } ) => {
	// For local development, use existing state if it exists and isn't stale.
	if ( ! process.env.CI ) {
		if ( ! isAuthStateStale( editorStorageFile ) ) {
			console.log( 'Using existing editor state.' );
			return;
		}
	}

	await addSupportSessionDetectedCookie( page, project );
	await loginAsEditor( page, editor );
} );
