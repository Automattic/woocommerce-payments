/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import { useMerchant } from '../../../utils/helpers';

test.describe( 'Merchant deposits', () => {
	// Use the merchant user for this test suite.
	useMerchant();

	test( 'Load the deposits list page', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/payouts'
		);

		// Wait for the deposits table to load.
		await page
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		await expect(
			page.getByRole( 'heading', {
				name: 'Payout history',
			} )
		).toBeVisible();
	} );

	test( 'Select deposits list advanced filters', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/payouts'
		);

		// Wait for the deposits table to load.
		await page
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		// Open the advanced filters.
		await page.getByRole( 'button', { name: 'All payouts' } ).click();
		await page.getByRole( 'button', { name: 'Advanced filters' } ).click();

		// Select a filter
		await page.getByRole( 'button', { name: 'Add a Filter' } ).click();
		await page.getByRole( 'button', { name: 'Status' } ).click();

		// Select a filter option
		await page
			.getByLabel( 'Select a payout status', {
				exact: true,
			} )
			.selectOption( 'Pending' );

		// Scroll to the top to ensure the sticky header doesn't cover the filters.
		await page.evaluate( () => {
			window.scrollTo( 0, 0 );
		} );

		// Apply the filter and confirm it drives the filtered list via the URL query.
		await page.getByRole( 'link', { name: 'Filter', exact: true } ).click();
		await expect( page ).toHaveURL( /status_is=/ );
		await expect(
			page.locator( '.woocommerce-table__table.is-loading' )
		).toHaveCount( 0 );
	} );
} );
