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
	wpAdminLogin,
} from '../utils/helpers';

// See https://playwright.dev/docs/auth#multiple-signed-in-roles
const {
	users: { admin, customer },
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

setup( 'authenticate as admin', async ( { page } ) => {
	// For local development, use existing state if it exists and isn't stale.
	if ( ! process.env.CI ) {
		if ( ! isAuthStateStale( merchantStorageFile ) ) {
			console.log( 'Using existing merchant state.' );
			return;
		}
	}

	// Sign in as admin user and save state
	let adminLoggedIn = false;
	const adminRetries = 5;
	for ( let i = 0; i < adminRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as admin...' );
			await wpAdminLogin( page, admin );
			await page.waitForLoadState( 'domcontentloaded' );
			await page.goto( `/wp-admin` );
			await page.waitForLoadState( 'domcontentloaded' );

			await expect(
				page.getByRole( 'heading', { name: 'Dashboard' } )
			).toBeVisible();

			console.log( 'Logged-in as admin successfully.' );
			adminLoggedIn = true;
			break;
		} catch ( e ) {
			console.log(
				`Admin log-in failed, Retrying... ${ i }/${ adminRetries }`
			);
			console.log( e );
		}
	}

	if ( ! adminLoggedIn ) {
		throw new Error(
			'Cannot proceed e2e test, as admin login failed. Please check if the test site has been setup correctly.'
		);
	}

	// End of authentication steps.

	await page.context().storageState( { path: merchantStorageFile } );
} );

setup( 'authenticate as customer', async ( { page } ) => {
	// For local development, use existing state if it exists and isn't stale.
	if ( ! process.env.CI ) {
		if ( ! isAuthStateStale( customerStorageFile ) ) {
			console.log( 'Using existing customer state.' );
			return;
		}
	}

	// Sign in as customer user and save state
	let customerLoggedIn = false;
	const customerRetries = 5;
	for ( let i = 0; i < customerRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as customer...' );
			await wpAdminLogin( page, customer );

			await page.goto( `/my-account` );
			await expect(
				page.locator(
					'.woocommerce-MyAccount-navigation-link--customer-logout'
				)
			).toBeVisible();
			await expect(
				page.locator( 'div.woocommerce-MyAccount-content > p >> nth=0' )
			).toContainText( 'Hello' );

			console.log( 'Logged-in as customer successfully.' );
			customerLoggedIn = true;
			break;
		} catch ( e ) {
			console.log(
				`Customer log-in failed. Retrying... ${ i }/${ customerRetries }`
			);
			console.log( e );
		}
	}

	if ( ! customerLoggedIn ) {
		throw new Error(
			'Cannot proceed e2e test, as customer login failed. Please check if the test site has been setup correctly.'
		);
	}
	// End of authentication steps.

	await page.context().storageState( { path: customerStorageFile } );
} );
