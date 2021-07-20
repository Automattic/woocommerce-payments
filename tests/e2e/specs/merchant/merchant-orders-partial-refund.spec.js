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

const orders = [];

describe( 'Order > Partial refund', () => {
	beforeAll( async () => {
		// mytodo use api to create the orders
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );

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
		const orderTotalField = await page.$(
			'.woocommerce-order-overview__total strong'
		);
		const orderId = await orderIdField.evaluate( ( el ) => el.innerText );
		const orderTotal = await orderTotalField.evaluate(
			( el ) => el.innerText
		);
		orders.push( {
			orderId: orderId,
			orderTotal: Number( orderTotal.replace( '$', '' ) ),
		} );

		// Login as merchant
		await merchant.login();
	} );

	afterAll( async () => {
		await takeScreenshot( 'my-wip' ); // mytodo remove later
		await merchant.logout();
	} );

	it( 'should refund a partial amount', async () => {
		const orderIndex = 0;
		const { orderId, orderTotal } = orders[ orderIndex ];
		const refundAmount = 5;
		const refundAmountString = refundAmount.toFixed( 2 );
		const refundReason = `Partial refund of $${ refundAmountString }`;
		const netPayment = ( orderTotal - refundAmount ).toFixed( 2 );

		await merchant.goToOrder( orderId );

		// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
		await page.removeAllListeners( 'dialog' );

		// Click the Refund button
		await expect( page ).toClick( 'button.refund-items' );

		// Do a partial refund of $5
		await expect( page ).toFill( '.refund_line_total', refundAmountString );
		await expect( page ).toFill( '#refund_reason', refundReason );
		await expect( page ).toMatchElement( '.do-api-refund', {
			text: `Refund $${ refundAmountString } via WooCommerce Payments`,
		} );
		const refundDialog = await expect( page ).toDisplayDialog( async () => {
			await expect( page ).toClick( 'button.do-api-refund' );
		} );
		await refundDialog.accept();
		await uiUnblocked();
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );

		// Verify the product line item shows the refunded amount
		await expect( page ).toMatchElement( '.line_cost .refunded .amount', {
			text: `$${ refundAmountString }`,
		} );

		// Verify the refund shows in the list with the amount
		await expect( page ).toMatchElement( '.refund > .line_cost', {
			text: `-$${ refundAmountString }`,
		} );

		// Verify system note was added
		await expect( page ).toMatchElement( '.system-note', {
			text: `A refund of $${ refundAmountString } was successfully processed using WooCommerce Payments.`,
		} );
		await expect( page ).toMatchElement( '.system-note', {
			text: `Reason: ${ refundReason }`,
		} );

		// Verify "Refunded" and "Net Payment" values in Order Totals section
		await expect( page ).toMatchElement(
			'.wc-order-totals .total.refunded-total',
			{
				text: `-$${ refundAmountString }`,
			}
		);
		await expect( page ).toMatchElement( '.wc-order-totals .total', {
			text: `$${ netPayment }`,
		} );

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
				text: `A payment of $${ refundAmountString } was successfully refunded.`,
			} ),
			expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
				text: `$${ refundAmountString } will be deducted from a future deposit.`,
			} ),
			expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
				text: 'Payment status changed to Partial Refund.',
			} ),
		] );
	} );
} );
