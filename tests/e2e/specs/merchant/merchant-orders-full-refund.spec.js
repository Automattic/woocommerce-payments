/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, takeScreenshot } from '../../utils';
import { fillCardDetails, setupProductCheckout } from '../../utils/payments';

let orderId;
let orderAmount;

describe( 'Order > Full refund', () => {
	beforeAll( async () => {
		// Place an order to refund later
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

		// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
		await page.removeAllListeners( 'dialog' );

		// Get the order price
		const priceElement = await page.$( '.woocommerce-Price-amount' );
		orderAmount = await page.evaluate(
			( el ) => el.textContent,
			priceElement
		);
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process a full refund for an order', async () => {
		// Click the Refund button
		await expect( page ).toClick( 'button.refund-items' );

		// Verify the refund section shows
		await page.waitForSelector( 'div.wc-order-refund-items', {
			visible: true,
		} );

		// Verify Refund via WooCommerce Payments button is displayed
		await page.waitForSelector( 'button.do-api-refund' );

		// Initiate a refund
		await expect( page ).toFill( '.refund_line_total', orderAmount );
		await expect( page ).toFill( '#refund_reason', 'No longer wanted' );

		await expect( page ).toMatchElement( '.do-api-refund', {
			text: `Refund ${ orderAmount } via WooCommerce Payments`,
		} );
		await takeScreenshot( 'merchant-orders-full-refund_refunding' );

		const refundDialog = await expect( page ).toDisplayDialog( async () => {
			await expect( page ).toClick( 'button.do-api-refund' );
		} );

		// Accept the refund
		await refundDialog.accept();

		await uiUnblocked();

		await page.waitForNavigation( { waitUntil: 'networkidle0' } );

		await Promise.all( [
			// Verify the product line item shows the refunded amount
			expect( page ).toMatchElement( '.line_cost .refunded', {
				text: `-${ orderAmount }`,
			} ),

			// Verify the refund shows in the list with the amount
			expect( page ).toMatchElement( '.refund > .line_cost', {
				text: `-${ orderAmount }`,
			} ),

			// Verify system note was added
			expect( page ).toMatchElement( '.system-note', {
				text: `A refund of ${ orderAmount } USD was successfully processed using WooCommerce Payments. Reason: No longer wanted`,
			} ),
		] );
		await takeScreenshot( 'merchant-orders-full-refund_refunded' );
	} );

	it( 'should be able to view a refunded transaction', async () => {
		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		await merchantWCP.openPaymentDetails( paymentDetailsLink );
		await takeScreenshot( 'merchant-orders-full-refund_payment-details' );

		// Verify the transaction timeline reflects the refund events
		await Promise.all( [
			expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
				text: `A payment of ${ orderAmount } USD was successfully refunded.`,
			} ),
			expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
				text: 'Payment status changed to Refunded.',
			} ),
		] );
	} );
} );
