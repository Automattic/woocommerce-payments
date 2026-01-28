/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';

test.describe( 'Merchant deposits', { tag: '@merchant' }, () => {
	test( 'Load the deposits list page', async ( { adminPage } ) => {
		await adminPage.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/payouts'
		);

		// Wait for the deposits table to load.
		await adminPage
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		await expect(
			adminPage.getByRole( 'heading', {
				name: 'Payout history',
			} )
		).toBeVisible();
	} );

	test( 'Select deposits list advanced filters', async ( { adminPage } ) => {
		await adminPage.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/payouts'
		);

		// Wait for the deposits table to load.
		await adminPage
			.locator( '.woocommerce-table__table.is-loading' )
			.waitFor( { state: 'hidden' } );

		// Open the advanced filters.
		await adminPage.getByRole( 'button', { name: 'All payouts' } ).click();
		await adminPage
			.getByRole( 'button', { name: 'Advanced filters' } )
			.click();

		// Select a filter
		await adminPage.getByRole( 'button', { name: 'Add a Filter' } ).click();
		await adminPage.getByRole( 'button', { name: 'Status' } ).click();

		// Select a filter option
		await adminPage
			.getByLabel( 'Select a payout status', {
				exact: true,
			} )
			.selectOption( 'Pending' );

		// Scroll to the top to ensure the sticky header doesn't cover the filters.
		await adminPage.evaluate( () => {
			window.scrollTo( 0, 0 );
		} );
		// TODO: This visual regression test is not flaky, but we should revisit the approach.
		// await expect(
		// 	adminPage.locator( '.woocommerce-filters' ).last()
		// ).toHaveScreenshot();
	} );
} );
