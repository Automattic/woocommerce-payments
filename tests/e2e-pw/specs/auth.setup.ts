/* eslint-disable no-console */
/**
 * External dependencies
 */
import { test as setup, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import testConfig from '../../e2e/config/default.json';

// See https://playwright.dev/docs/auth#multiple-signed-in-roles
const {
	users: { admin, customer },
} = testConfig;
process.env.ADMINSTATE = `tests/e2e-pw/.auth/admin.json`;
process.env.CUSTOMERSTATE = `tests/e2e-pw/.auth/customer.json`;

setup( 'authenticate as admin', async ( { page } ) => {
	const adminFile = process.env.ADMINSTATE;
	// Sign in as admin user and save state
	let adminLoggedIn = false;
	const adminRetries = 5;
	for ( let i = 0; i < adminRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as admin...' );
			await page.goto( `/wp-admin` );
			await page.locator( 'input[name="log"]' ).fill( admin.username );
			await page.locator( 'input[name="pwd"]' ).fill( admin.password );
			await page.locator( 'text=Log In' ).click();
			await page.waitForLoadState( 'networkidle' );
			await page.goto( `/wp-admin` );
			await page.waitForLoadState( 'domcontentloaded' );

			await expect( page.locator( 'div.wrap > h1' ) ).toHaveText(
				'Dashboard'
			);

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

	await page.context().storageState( { path: adminFile } );
} );

setup( 'authenticate as customer', async ( { page } ) => {
	const customerFile = process.env.CUSTOMERSTATE;

	// Sign in as customer user and save state
	let customerLoggedIn = false;
	const customerRetries = 5;
	for ( let i = 0; i < customerRetries; i++ ) {
		try {
			console.log( 'Trying to log-in as customer...' );
			await page.goto( `/wp-admin` );
			await page.locator( 'input[name="log"]' ).fill( customer.username );
			await page.locator( 'input[name="pwd"]' ).fill( customer.password );
			await page.locator( 'text=Log In' ).click();

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

	await page.context().storageState( { path: customerFile } );
} );
