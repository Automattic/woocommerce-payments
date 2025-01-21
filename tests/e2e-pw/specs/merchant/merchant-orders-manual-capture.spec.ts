/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getShopper, getMerchant } from '../../utils/helpers';
import { goToOrder } from '../../utils/merchant-navigation';
import {
	activateCaptureLater,
	deactivateCaptureLater,
} from '../../utils/merchant';
import { placeOrderWithOptions } from '../../utils/shopper';

/**
 * Local variables.
 */
let orderId;
let merchantPage;

test.describe( 'Order > Manual Capture', () => {
	test.beforeAll( async ( { browser } ) => {
		// Merchant go to settings, enable capture later, and then save.
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		await activateCaptureLater( merchantPage );

		// Shopper add items to cart, fill in the checkout, place an order.
		const { shopperPage } = await getShopper( browser );
		orderId = await placeOrderWithOptions( shopperPage );
	} );

	test.afterAll( async () => {
		// Merchant go to settings, disable capture later, and then save.
		await deactivateCaptureLater( merchantPage );
	} );

	test( 'should create an "On hold" order then capture the charge', async () => {
		// Merchant go to the order.
		await goToOrder( merchantPage, orderId );

		// Confirm order status is 'On hold', and that there's an 'authorized' note.
		await expect( merchantPage.getByTitle( 'On hold' ) ).toBeVisible();
		await expect(
			merchantPage.getByText(
				'A payment of $18.00 was authorized using WooPayments'
			)
		).toBeVisible();

		// Set select to 'capture_charge' and submit.
		await merchantPage
			.locator( '#woocommerce-order-actions select' )
			.selectOption( 'capture_charge' );
		await merchantPage
			.locator( '#woocommerce-order-actions li#actions button' ) // Using locator due to there are several buttons "named" Update.
			.click();

		// After the page reloads, confirm the order is processing and we have a 'captured' order note.
		await expect( merchantPage.getByTitle( 'Processing' ) ).toBeVisible();
		await expect(
			merchantPage.getByText(
				'A payment of $18.00 was successfully captured using WooPayments'
			)
		).toBeVisible();
	} );
} );
