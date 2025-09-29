/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import { config } from '../config/default';

test.describe(
	'A basic set of tests to ensure WP, wp-admin and my-account load',
	() => {
		test( 'Load the home page', async ( { page } ) => {
			await page.goto( '/' );
			const title = page.locator( 'h1.site-title' );
			await expect( title ).toHaveText(
				/WooCommerce Core E2E Test Suite/i
			);
		} );

		test.describe( 'Sign in as admin', () => {
			test( 'Load Payments Overview', async ( { page } ) => {
				await qit.loginAsAdmin( page );

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
			test( 'Load customer my account page', async ( { page } ) => {
				const { username, password } = config.users.customer;
				await qit.loginAs( page, username, password );

				await page.goto( '/my-account' );
				const title = page.locator( 'h1.entry-title' );
				await expect( title ).toHaveText( 'My account' );
			} );
		} );
	}
);
