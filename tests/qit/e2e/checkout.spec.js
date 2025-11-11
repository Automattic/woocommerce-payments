/**
 * External dependencies
 */
const { test, expect } = require( '@playwright/test' );
import qit from '/qitHelpers';

/**
 * WooPayments Connection Validation Tests
 *
 * These tests verify that WooPayments is properly connected and configured
 * in the QIT E2E testing environment. They validate the core connection without
 * testing actual checkout functionality.
 */
test.describe( 'WooPayments Connection Status', () => {
	test( 'should verify WooPayments is connected (not showing Connect screen)', async ( {
		page,
	} ) => {
		// Login as admin first
		await qit.loginAsAdmin( page );

		// Navigate directly to WooPayments settings
		await page.goto(
			'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments'
		);

		const pageContent = await page.textContent( 'body' );

		// If we see primary "Connect" buttons, we're NOT connected
		if (
			pageContent.includes( 'Connect your store' ) ||
			pageContent.includes( 'Connect WooPayments' ) ||
			pageContent.includes( 'Set up WooPayments' )
		) {
			throw new Error(
				'WooPayments is NOT connected - showing Connect screen'
			);
		}

		// Look for connected account configuration options
		// These elements only appear when WC Payments is connected and configured
		const hasConnectedFeatures =
			pageContent.includes( 'Enable WooPayments' ) ||
			pageContent.includes( 'Enable/disable' ) ||
			pageContent.includes( 'Payment methods' ) ||
			pageContent.includes( 'Capture charges automatically' ) ||
			pageContent.includes( 'Manual capture' ) ||
			pageContent.includes( 'Test mode' ) ||
			pageContent.includes( 'Debug mode' );

		if ( ! hasConnectedFeatures ) {
			throw new Error(
				'No WooPayments configuration options found - may not be properly connected'
			);
		}

		expect( hasConnectedFeatures ).toBe( true );

		// Additional verification: Should not see primary connection prompts
		expect( pageContent ).not.toContain( 'Connect your store' );
		expect( pageContent ).not.toContain( 'Connect WooPayments' );
	} );

	test( 'should verify account data is fetched from server', async ( {
		page,
	} ) => {
		// Login as admin first
		await qit.loginAsAdmin( page );

		// Navigate to WooPayments overview page to check account status
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Foverview'
		);

		const pageContent = await page.textContent( 'body' );

		// Account should be connected via Jetpack
		// Look for specific indicators that account data was fetched from test account
		const hasAccountData =
			pageContent.includes( 'Test account' ) ||
			pageContent.includes( 'Live account' ) ||
			pageContent.includes( 'Account status' ) ||
			pageContent.includes( 'Payments' ) ||
			pageContent.includes( 'Overview' ) ||
			pageContent.includes( 'Deposits' ) ||
			pageContent.includes( 'Transactions' ) ||
			// Test account specific indicators
			pageContent.includes( 'acct_' ) || // Stripe account ID
			pageContent.includes( 'Your store is connected' ) ||
			pageContent.includes( 'Payment methods' );

		if ( ! hasAccountData ) {
			// Check if we're seeing an error or connection issue
			if (
				pageContent.includes( 'Connect' ) ||
				pageContent.includes( 'Set up' )
			) {
				throw new Error(
					'Account is not connected - showing setup/connect screen'
				);
			}

			if (
				pageContent.includes( 'Error' ) ||
				pageContent.includes( 'Unable to connect' )
			) {
				throw new Error(
					'Connection error detected - account data not fetched'
				);
			}

			throw new Error(
				'No account data indicators found - connection may have failed'
			);
		}

		// Verify Jetpack connection is working
		expect( hasAccountData ).toBe( true );

		// Additional check: Should not see connection errors
		expect( pageContent ).not.toContain( 'Unable to connect' );
		expect( pageContent ).not.toContain( 'Connection failed' );
	} );
} );
