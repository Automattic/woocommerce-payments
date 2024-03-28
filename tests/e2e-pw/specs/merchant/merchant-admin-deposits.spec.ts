/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import { useMerchant } from '../../utils/helpers';

test.describe( 'Merchant deposits', () => {
	// Use the merchant user for this test suite.
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
	} );

	test( 'Select deposits list advanced filters', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/deposits'
		);

		// Wait for the deposits table to load.
		await page
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		// Open the advanced filters.
		await page.getByRole( 'button', { name: 'All deposits' } ).click();
		await page.getByRole( 'button', { name: 'Advanced filters' } ).click();

		// Select a filter
		await page.getByRole( 'button', { name: 'Add a Filter' } ).click();
		await page.getByRole( 'button', { name: 'Status' } ).click();

		// Select a filter option
		await page
			.getByLabel( 'Select a deposit status', {
				exact: true,
			} )
			.selectOption( 'Pending' );

		// Scroll to the top to ensure the sticky header doesn't cover the filters.
		await page.evaluate( () => {
			window.scrollTo( 0, 0 );
		} );
		await expect(
			page.locator( '.woocommerce-filters' ).last()
		).toHaveScreenshot();
	} );
} );
