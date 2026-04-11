/**
 * External dependencies
 */
import { test, expect } from '../fixtures/auth';

test.describe(
	'A basic set of tests to ensure WP, wp-admin and my-account load',
	() => {
		test( 'Load the home page', async ( { page } ) => {
			await page.goto( '/' );
			// Verify the page loaded by checking that a site title exists
			const title = page.locator( 'h1.site-title' );
			await expect( title ).toBeVisible();
		} );

		test.describe( 'Sign in as admin', () => {
			test( 'Load Payments Overview', async ( { adminPage } ) => {
				await adminPage.goto(
					'/wp-admin/admin.php?page=wc-admin&path=/payments/overview'
				);
				await adminPage.waitForLoadState( 'domcontentloaded' );
				await expect(
					adminPage.getByRole( 'heading', { name: 'Overview' } )
				).toBeVisible();
			} );
		} );

		test.describe( 'Sign in as customer', () => {
			test( 'Load customer my account page', async ( {
				customerPage,
			} ) => {
				await customerPage.goto( '/my-account' );
				const title = customerPage.locator( 'h1.entry-title' );
				await expect( title ).toHaveText( 'My account' );
			} );
		} );
	}
);
