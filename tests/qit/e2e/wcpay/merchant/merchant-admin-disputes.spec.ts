/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

test.describe( 'Merchant disputes', () => {
	// Use the merchant user for this test suite.
	useMerchant();

	test( 'Load the disputes list page', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/disputes'
		);

		// Wait for the disputes table to load.
		await expect(
			page.locator( '.woocommerce-table__table.is-loading' )
		).toHaveCount( 0 );

		// .nth( 1 ) defines the second instance of the Disputes heading, which is in the table.
		expect(
			page.getByRole( 'heading', { name: 'Disputes' } ).nth( 1 )
		).toBeVisible();
	} );
} );
