/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */

import { config } from '../../config/default';
import * as shopper from '../../utils/shopper';

test.describe( 'Successful purchase', () => {
	test.beforeEach( async ( { page } ) => {
		await shopper.addCartProduct( page );

		await page.goto( '/checkout/' );
		await shopper.fillBillingAddress(
			page,
			config.addresses.customer.billing
		);
	} );

	test( 'using a basic card', async ( { page } ) => {
		await shopper.fillCardDetails( page );
		await shopper.placeOrder( page );

		await expect(
			page.getByText( 'Order received' ).first()
		).toBeVisible();
	} );

	test( 'using a 3DS card', async ( { page } ) => {
		await shopper.fillCardDetails( page, config.cards[ '3ds' ] );
		await shopper.placeOrder( page );
		await shopper.confirmCardAuthentication( page );

		await expect(
			page.getByText( 'Order received' ).first()
		).toBeVisible();
	} );
} );
