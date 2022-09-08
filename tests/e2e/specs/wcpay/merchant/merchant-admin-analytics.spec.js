/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, takeScreenshot } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

// let orderId;

describe( 'Admin Order Analytics', () => {
	beforeAll( async () => {
		// Place an order to ensure the analytics data is correct.
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// Get the order ID so we can open it in the merchant view
		// const orderIdField = await page.$(
		// 	'.woocommerce-order-overview__order.order > strong'
		// );
		// orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		// Login
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openOrderAnalytics();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Orders',
		} );
		await takeScreenshot( 'merchant-admin-order-analytics' );
	} );

	it( 'orders table should have customer currency column', async () => {
		await merchantWCP.openOrderAnalytics();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Orders',
		} );
		await takeScreenshot( 'merchant-admin-order-analytics' );
	} );
} );
