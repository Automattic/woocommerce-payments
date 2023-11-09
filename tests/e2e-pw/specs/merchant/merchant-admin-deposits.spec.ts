/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

test.describe( 'Merchant deposits', () => {
	test.use( {
		storageState: process.env.ADMINSTATE,
	} );

	test( 'Load the deposits list page', async ( { page } ) => {
		await page.goto(
			'/wp-admin/admin.php?page=wc-admin&path=/payments/deposits'
		);
		expect(
			page.getByRole( 'heading', {
				name: 'Deposit history',
			} )
		).toBeVisible();
		await page.waitForLoadState( 'networkidle' );
		await expect( page ).toHaveScreenshot();
	} );
} );
