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
 *
 */
let orderId;

test.describe( 'Order > Manual Capture', () => {
	test.beforeEach( async ( { browser } ) => {
		//test.setTimeout( 60000 ); Do we need this?

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

	test( 'should create an order with status "On Hold"', async ( {
		browser,
	} ) => {
		// Merchant go to the order and confirm that it is On Hold.
		const { merchantPage } = await getMerchant( browser );
		await goToOrder( merchantPage, orderId );
		await expect( merchantPage.getByTitle( 'On hold' ) ).toHaveText(
			'On hold'
		);
	} );

	test( 'should create an order note mentioning authorization', async ( {
		browser,
	} ) => {
		// Merchant go to order and confirm order note authorized.
		const { merchantPage } = await getMerchant( browser );
		await goToOrder( merchantPage, orderId );
		await expect(
			merchantPage.getByText(
				/A payment of \$\d+\.\d{2}.* was authorized using WooPayments/
			)
		).toBeVisible();
	} );

	test( 'should capture the charge', async ( { browser } ) => {
		// Merchant go to order, set to capture charge, submit, and confirm order note.
		const { merchantPage } = await getMerchant( browser );
		await goToOrder( merchantPage, orderId );
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
