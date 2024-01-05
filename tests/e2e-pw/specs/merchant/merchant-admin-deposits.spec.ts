/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import { useMerchant } from '../../utils/helpers';

test.describe( 'Merchant deposits', () => {
	useMerchant();

	test( 'Load the deposits list page', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/deposits'
		);

		// Wait for the deposits table to load.
		await page
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		expect(
			page.getByRole( 'heading', {
				name: 'Deposit history',
			} )
		).toBeVisible();

		await expect(
			page.locator( '.woocommerce-payments-page' ).last()
		).toHaveScreenshot();
	} );
} );
