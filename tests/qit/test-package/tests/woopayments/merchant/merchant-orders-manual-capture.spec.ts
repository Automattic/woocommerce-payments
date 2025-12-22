/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import {
	activateCaptureLater,
	deactivateCaptureLater,
	goToOrder,
} from '../../../utils/merchant';
import { placeOrderWithOptions } from '../../../utils/shopper';

test.describe( 'Order > Manual Capture', { tag: '@merchant' }, () => {
	let orderId: string;
	let wasInitiallyEnabled: boolean;

	test.beforeAll( async ( { adminPage } ) => {
		// Merchant go to settings, enable capture later, and then save.
		wasInitiallyEnabled = await activateCaptureLater( adminPage );
	} );

	test.afterAll( async ( { adminPage } ) => {
		// Restore original capture later state
		if ( ! wasInitiallyEnabled ) {
			await deactivateCaptureLater( adminPage );
		}
	} );

	test(
		'should create an "On hold" order then capture the charge',
		{ tag: '@critical' },
		async ( { adminPage, customerPage } ) => {
			// Shopper add items to cart, fill in the checkout, place an order.
			orderId = await placeOrderWithOptions( customerPage );
			// Merchant go to the order.
			await goToOrder( adminPage, orderId );

			const orderTotal = await adminPage
				.getByRole( 'row', { name: 'Order Total: $' } )
				.locator( 'bdi' )
				.textContent();

			// Confirm order status is 'On hold', and that there's an 'authorized' note.
			await expect( adminPage.getByTitle( 'On hold' ) ).toBeVisible();
			await expect(
				adminPage.getByText(
					`A payment of ${ orderTotal } was authorized using WooPayments`
				)
			).toBeVisible();

			// Set select to 'capture_charge' and submit.
			await adminPage
				.locator( '#woocommerce-order-actions select' )
				.selectOption( 'capture_charge' );
			await adminPage
				.locator( '#woocommerce-order-actions li#actions button' ) // Using locator due to there are several buttons "named" Update.
				.click();

			// After the page reloads, confirm the order is processing and we have a 'captured' order note.
			await expect( adminPage.getByTitle( 'Processing' ) ).toBeVisible();
			await expect(
				adminPage.getByText(
					`A payment of ${ orderTotal } was successfully captured using WooPayments`
				)
			).toBeVisible();
		}
	);
} );
