/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import qit from '/qitHelpers';

const CUSTOMER_USERNAME = process.env.QIT_CUSTOMER_USERNAME || 'testcustomer';
const CUSTOMER_PASSWORD = process.env.QIT_CUSTOMER_PASSWORD || 'testpass123';

const loginAsCustomer = async ( page ) => {
	await page.goto( '/my-account' );
	await page.waitForLoadState( 'domcontentloaded' );

	const logoutLink = page.locator(
		'.woocommerce-MyAccount-navigation-link--customer-logout'
	);
	if ( await logoutLink.count() ) {
		return;
	}

	const usernameInput = page.locator( 'input#username' );
	const passwordInput = page.locator( 'input#password' );

	await expect( usernameInput ).toBeVisible();
	await usernameInput.fill( CUSTOMER_USERNAME );
	await passwordInput.fill( CUSTOMER_PASSWORD );
	await page.locator( 'button[name="login"]' ).click();

	await expect( logoutLink ).toBeVisible();
};

test.describe( 'Basic WooPayments smoke tests', () => {
	test( 'Load the home page', async ( { page } ) => {
		await page.goto( '/' );
		await page.waitForLoadState( 'domcontentloaded' );

		const siteTitle = page.locator( 'h1.site-title' );
		if ( await siteTitle.count() ) {
			await expect( siteTitle ).toContainText(
				/WooPayments|WooCommerce/i
			);
		} else {
			await expect( page.locator( 'body' ) ).toContainText(
				/Woo|WordPress/i
			);
		}
	} );

	test( 'Load Payments overview as admin', async ( { page } ) => {
		await qit.loginAsAdmin( page );
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview'
		);
		await page.waitForLoadState( 'domcontentloaded' );

		await expect(
			page.getByRole( 'heading', { name: /Overview|Payments/i } )
		).toBeVisible( { timeout: 15000 } );
	} );

	test( 'Load customer My Account page', async ( { page } ) => {
		await loginAsCustomer( page );

		const myAccountHeading = page.locator( 'h1.entry-title' );
		if ( await myAccountHeading.count() ) {
			await expect( myAccountHeading ).toHaveText( /My account/i );
		} else {
			await expect( page.locator( 'body' ) ).toContainText(
				/My account/i
			);
		}
	} );
} );

/**
 * Test plugin activation and basic WooCommerce functionality
 */
test( 'Verify WooCommerce Payments plugin activation', async ( { page } ) => {
	await qit.loginAsAdmin( page );

	// Check plugins page to verify WooCommerce Payments is active
	await page.goto( '/wp-admin/plugins.php' );

	// Look for the WooCommerce Payments plugin row
	const pluginRow = page.locator( 'tr[data-plugin*="woocommerce-payments"]' );
	await expect( pluginRow ).toBeVisible();

	// Verify it shows as activated
	await expect( pluginRow.locator( '.deactivate' ) ).toBeVisible();
} );
