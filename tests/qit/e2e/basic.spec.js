/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Simple QIT E2E test - bare minimum to verify QIT works
 */
test( 'Load home page', async ( { page } ) => {
	await page.goto( '/' );

	// Just check that we can load the page and title exists
	await expect( page ).toHaveTitle( /.*/ );
} );

/**
 * Test admin authentication and WooPayments plugin access
 */
test( 'Access WooPayments as admin', async ( { page } ) => {
	// Use QIT helper to login as admin
	await qit.loginAsAdmin( page );

	// Navigate to WooPayments settings
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview'
	);

	// We should see the Payments admin route load
	await expect(
		page.locator( 'h1:not(.screen-reader-text)' ).first()
	).toContainText( /Settings|Payments|Overview/, { timeout: 15000 } );

	// Check that we can successfully load the WooPayments interface
	// Either we get the overview (if fully connected) OR the onboarding.
	const isOnboarding = page.url().includes( 'onboarding' );
	const isOverview = page.url().includes( 'payments' );

	// We should be on either the onboarding or overview page (both indicate success)
	expect( isOnboarding || isOverview ).toBe( true );

	// If we're on onboarding, it should be functional (not errored)
	if ( isOnboarding ) {
		// The onboarding page should load without errors
		await expect( page.locator( 'body' ) ).not.toHaveText(
			/500|404|Fatal error/
		);
	}
} );

/**
 * Test plugin activation and basic WooCommerce functionality
 */
test( 'Verify WooCommerce Payments plugin activation', async ( { page } ) => {
	await qit.loginAsAdmin( page );

	// Check plugins page to verify WooCommerce Payments is active
	await page.goto( '/wp-admin/plugins.php' );

	// Look for the WooCommerce Payments plugin row (exclude update row)
	const pluginRow = page.locator(
		'tr[data-plugin*="woocommerce-payments"]:not(.plugin-update-tr)'
	);
	await expect( pluginRow ).toBeVisible();

	// Verify it shows as activated
	await expect( pluginRow.locator( '.deactivate' ) ).toBeVisible();
} );
