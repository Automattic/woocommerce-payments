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
import { saveWooPaymentsSettings } from '../../utils/merchant';
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
	test.beforeEach( async ( { browser } ) => {
		// Merchant go to settings, enable capture later, and then save.
		const { merchantPage } = await getMerchant( browser );
		await goToWooPaymentsSettings( merchantPage );
		await merchantPage.getByTestId( 'capture-later-checkbox' ).click();
		await merchantPage
			.getByRole( 'button', { name: 'Enable manual capture' } )
			.click();
		await saveWooPaymentsSettings( merchantPage );

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

	test.afterEach( async ( { browser } ) => {
		// Merchant go to settings, disable capture later, and then save.
		const { merchantPage } = await getMerchant( browser );
		await goToWooPaymentsSettings( merchantPage );
		await merchantPage.getByTestId( 'capture-later-checkbox' ).click();
		await saveWooPaymentsSettings( merchantPage );
	} );

	test( 'should create an "On hold" order then capture the charge', async ( {
		browser,
	} ) => {
		// Merchant go to the order.
		const { merchantPage } = await getMerchant( browser );
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
