/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import { useMerchant, useShopper } from '../utils/helpers';

test.describe(
	'A basic set of tests to ensure WP, wp-admin and my-account load',
	() => {
		test( 'Load the home page', async ( { page } ) => {
			await page.goto( '/' );
			const title = page.locator( 'h1.site-title' );
			await expect( title ).toHaveText(
				/WooCommerce Payments E2E site/i
			);
		} );

		test.describe( 'Sign in as admin', () => {
			useMerchant();
			test( 'Load Payments Overview', async ( { page } ) => {
				await page.goto(
					'/wp-admin/admin.php?page=wc-admin&path=/payments/overview'
				);
				await page.waitForLoadState( 'domcontentloaded' );
				await expect(
					page.getByRole( 'heading', { name: 'Overview' } )
				).toBeVisible();
			} );
		} );

		test.describe( 'Sign in as customer', () => {
			useShopper();
			test( 'Load customer my account page', async ( { page } ) => {
				await page.goto( '/my-account' );
				const title = page.locator( 'h1.entry-title' );
				await expect( title ).toHaveText( 'My account' );
			} );
		} );
	}
);
