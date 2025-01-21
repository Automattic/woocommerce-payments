/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getShopper, getMerchant } from '../../utils/helpers';
import {
	goToOrder,
	goToWooPaymentsSettings,
} from '../../utils/merchant-navigation';
import {
	activateCaptureLater,
	deactivateCaptureLater,
} from '../../utils/merchant';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../utils/shopper';
import { goToShop } from '../../utils/shopper-navigation';

/**
 * Local variables.
 */
let orderId;

test.describe( 'Order > Manual Capture', () => {
	let merchantPage;

	test.beforeAll( async ( { browser } ) => {
		// Merchant go to settings, enable capture later, and then save.
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		await activateCaptureLater( merchantPage );

		// Shopper add items to cart, fill in the checkout, place an order.
		const { shopperPage } = await getShopper( browser );
		await emptyCart( shopperPage );
		await goToShop( shopperPage, 1 );
		await setupProductCheckout( shopperPage );
		await fillCardDetails( shopperPage );
		await focusPlaceOrderButton( shopperPage );
		await placeOrder( shopperPage );

		// Confirm that the order was placed and get the order number.
		await shopperPage.waitForURL( /\/order-received\//, {
			waitUntil: 'load',
		} );
		await expect(
			shopperPage.getByRole( 'heading', { name: 'Order received' } )
		).toBeVisible();
		const orderIdField = shopperPage.locator(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.innerText();
	} );

	test.afterAll( async () => {
		// Merchant go to settings, disable capture later, and then save.
		await deactivateCaptureLater( merchantPage );
	} );

	test( 'should create an "On hold" order then capture the charge', async () => {
		// Merchant go to the order.
		await goToOrder( merchantPage, orderId );

		// Confirm order status is 'On hold', and that there's an 'authorized' note.
		await expect( merchantPage.getByTitle( 'On hold' ) ).toHaveText(
			'On hold'
		);
		await expect(
			merchantPage.getByText(
				/A payment of \$\d+\.\d{2}.* was authorized using WooPayments/
			)
		).toBeVisible();

		// Set select to 'capture_charge', submit, and confirm 'captured' order note.
		merchantPage
			.locator( '#woocommerce-order-actions select' )
			.selectOption( 'capture_charge' );
		// Using locator due to there are several buttons "named" Update.
		merchantPage
			.locator( '#woocommerce-order-actions li#actions button' )
			.click();
		await expect(
			merchantPage.getByText(
				/A payment of \$\d+\.\d{2}.* was successfully captured using WooPayments/
			)
		).toBeVisible();
	} );
} );
