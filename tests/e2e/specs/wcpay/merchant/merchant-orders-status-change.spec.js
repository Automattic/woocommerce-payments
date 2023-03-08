/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

let orderId;

describe( 'Order > Status Change', () => {
	beforeAll( async () => {
		// Place an order to change its status later
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// Get the order ID so we can open it in the merchant view
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		// Login and open the order
		await merchant.login();
		await merchant.goToOrder( orderId );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should show Cancel Confirmation modal on status change and reset on selecting Do Nothing', async () => {
		// Select cancel from the order status dropdown.
		await expect( page ).toSelect(
			'select[name="order_status"]',
			'Cancelled'
		);

		// Verify the confirmation modal shows.
		await page.waitForSelector( 'div.cancel-confirmation-modal', {
			visible: true,
		} );

		// Click on Do Nothing.
		await expect( page ).toClick( 'button', { text: 'Do Nothing' } );

		// Verify the order status is set to processing.
		const selectedOrderStatus = await page.$( '.wc-order-status > span' );
		await expect(
			selectedOrderStatus.evaluate( ( el ) => el.innerText )
		).resolves.toBe( 'Processing' );
	} );

	it( 'should show Cancel Confirmation modal on status change and Cancel on confirmation', async () => {
		// Select cancel from the order status dropdown.
		await expect( page ).toSelect(
			'select[name="order_status"]',
			'Cancelled'
		);

		// Verify the confirmation modal shows.
		await page.waitForSelector( 'div.cancel-confirmation-modal', {
			visible: true,
		} );

		// Click on Cancel order.
		await expect( page ).toClick( 'button', { text: 'Cancel order' } );

		// Verify the order status is set to cancel.
		const selectedOrderStatus = await page.$( '.wc-order-status > span' );
		await expect(
			selectedOrderStatus.evaluate( ( el ) => el.innerText )
		).resolves.toBe( 'Cancelled' );
	} );
} );
