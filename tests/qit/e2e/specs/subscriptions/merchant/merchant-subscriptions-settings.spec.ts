/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

test.describe( 'WooCommerce > Settings > Subscriptions', () => {
	test(
		'Merchant should be able to load WooCommerce Subscriptions settings tab',
		{ tag: [ '@merchant', '@subscriptions' ] },
		async ( { adminPage } ) => {
			// Navigate to WooCommerce Settings > Subscriptions tab
			await adminPage.goto(
				'/wp-admin/admin.php?page=wc-settings&tab=subscriptions'
			);

			// Verify the Subscriptions menu item is visible
			const menuItem = adminPage.getByRole( 'main' ).getByRole( 'link', {
				name: 'Subscriptions',
				exact: true,
			} );
			await expect( menuItem ).toBeVisible();

			// Verify the Subscriptions heading is visible (alternative verification)
			const heading = adminPage
				.getByRole( 'heading', {
					name: 'Subscriptions',
				} )
				.first();
			await expect( heading ).toBeVisible();
		}
	);
} );
