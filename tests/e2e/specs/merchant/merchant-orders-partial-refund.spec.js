/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP } from '../../utils';
import { fillCardDetails, setupProductCheckout } from '../../utils/payments';

const card = config.get( 'cards.basic' );
const product1 = config.get( 'products.simple.name' );
const product2 = 'Belt';
const product3 = 'Hoodie';

/**
 * Elements:
 * - test title
 * - object containing the items to be ordered, and the quantities and amounts to be refunded
 */
const dataTable = [
	[
		'a single line item',
		{
			lineItems: [
				[ product1, 1 ],
				[ product2, 1 ],
			],
			refundInputs: [ { refundQty: 0, refundAmount: 5 } ],
		},
	],
	[
		'several line items',
		{
			lineItems: [
				[ product1, 1 ],
				[ product2, 2 ],
				[ product3, 1 ],
			],
			refundInputs: [
				{ refundQty: 1, refundAmount: 18 },
				{ refundQty: 1, refundAmount: 55 },
			],
		},
	],
];

describe.each( dataTable )(
	'Order > Partial refund',
	( testName, { lineItems, refundInputs } ) => {
		let orderId;
		let orderTotal;

		beforeAll( async () => {
			// Set up the test order
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' ),
				lineItems
			);
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );

			// Remember the order ID and order total. We will need them later.
			orderId = await page.$eval(
				'.woocommerce-order-overview__order.order > strong',
				( el ) => el.innerText
			);
			orderTotal = await page.$eval(
				'.woocommerce-order-overview__total strong',
				( el ) => Number( el.innerText.replace( '$', '' ) )
			);

			// Login as merchant
			await merchant.login();
		}, 200000 );

		afterAll( async () => {
			await merchant.logout();
		} );

		it( `should refund ${ testName }`, async () => {
			const refundReason = `Refunding ${ testName }`;
			const refundTotal = refundInputs
				.map( ( { refundAmount } ) => refundAmount )
				.reduce( ( acc, cur ) => acc + cur );
			const refundTotalString = refundTotal.toFixed( 2 );
			const netPayment = ( orderTotal - refundTotal ).toFixed( 2 );

			await merchant.goToOrder( orderId );

			// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
			await page.removeAllListeners( 'dialog' );

			// Click the Refund button
			await expect( page ).toClick( 'button.refund-items' );

			// Fill up the quantity and/or amount to be refunded per line item
			const rows = await page.$$( '#order_line_items tr.item' );
			for ( let i = 0; i < refundInputs.length; i++ ) {
				const { refundQty, refundAmount } = refundInputs[ i ];
				const row = rows[ i ];

				if ( refundQty ) {
					await expect( row ).toFill(
						'.refund_order_item_qty',
						`${ refundQty }`
					);
				} else {
					await expect( row ).toFill(
						'.refund_line_total',
						`${ refundAmount }`
					);
				}
			}

			// Fill up the rest of the form and complete the refund flow
			await expect( page ).toFill( '#refund_reason', refundReason );
			await expect( page ).toMatchElement( '.do-api-refund', {
				text: `Refund $${ refundTotalString } via WooCommerce Payments`,
			} );
			const refundDialog = await expect( page ).toDisplayDialog(
				async () => {
					await expect( page ).toClick( 'button.do-api-refund' );
				}
			);
			await refundDialog.accept();
			await uiUnblocked();
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Verify each line item shows the refunded quantity and/or amount
			const updatedRows = await page.$$( '#order_line_items tr.item' );
			for ( let i = 0; i < refundInputs.length; i++ ) {
				const { refundQty, refundAmount } = refundInputs[ i ];
				const row = updatedRows[ i ];

				if ( refundQty ) {
					await expect( row ).toMatchElement( '.quantity .refunded', {
						text: `-${ refundQty }`,
					} );
				}

				await expect( row ).toMatchElement( '.line_cost .refunded', {
					text: `-$${ refundAmount.toFixed( 2 ) }`,
				} );
			}

			// Verify the refund shows in the list with the amount
			await expect( page ).toMatchElement( '.refund > .line_cost', {
				text: `-$${ refundTotalString }`,
			} );

			// Verify system note was added
			await expect( page ).toMatchElement( '.system-note', {
				text: `refund of $${ refundTotalString }`,
			} );
			await expect( page ).toMatchElement( '.system-note', {
				text: `Reason: ${ refundReason }`,
			} );

			// Verify "Refunded" and "Net Payment" values in Order Totals section
			await expect( page ).toMatchElement(
				'.wc-order-totals .total.refunded-total',
				{
					text: `-$${ refundTotalString }`,
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

			// Verify the transaction timeline reflects the refund events
			await Promise.all( [
				expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
					text: `A payment of $${ refundTotalString } USD was successfully refunded.`,
				} ),
				expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
					text: `$${ refundTotalString } USD will be deducted from a future deposit.`,
				} ),
				expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
					text: 'Payment status changed to Partial refund.',
				} ),
			] );
		} );
	}
);
