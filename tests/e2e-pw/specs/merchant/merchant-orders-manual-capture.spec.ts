/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getShopper, getMerchant } from '../../utils/helpers';
import { goToWooPaymentsSettings } from '../../utils/merchant-navigation';
import { saveWooPaymentsSettings } from '../../utils/merchant';
import {
	setupProductCheckout,
	fillCardDetails,
	placeOrder,
} from '../../utils/shopper';

/**
 *
 */
let orderId;

test.describe( 'Order > Manual Capture', () => {
	test.beforeEach( async ( { browser } ) => {
		test.setTimeout( 120000 );

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
		await setupProductCheckout( shopperPage );
		await fillCardDetails( shopperPage );
		await placeOrder( shopperPage );

		// Confirm that the order was placed and get the order number.
		await expect( shopperPage.getByText( 'Order received' ) ).toBeVisible();
		const orderIdField = await shopperPage.locator(
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

	it( 'should create an order with status "On Hold"', async () => {
		await merchant.goToOrder( orderId );

		await expect( page ).toMatchElement(
			'#select2-order_status-container',
			{ text: 'On hold' }
		);
	} );

	it( 'should create an order note saying that payment was authorized ', async () => {
		await expect( page ).toMatchElement( '.system-note', {
			text: /A payment of \$\d+\.\d{2}.* was authorized/,
		} );
	} );

	it( 'should successfully capture charge', async () => {
		// Capture the charge
		await selectOrderAction( 'capture_charge' );

		// Verify that the order status is now "Processing"
		await expect( page ).toMatchElement(
			'#select2-order_status-container',
			{ text: 'Processing' }
		);

		// Verify that a system note about the capture was generated
		await expect( page ).toMatchElement( '.system-note', {
			text: /A payment of \$\d+\.\d{2}.* was successfully captured/,
		} );
	} );
} );
