/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../utils/payments';

let orderId;
const selectorQty = '.refund_order_item_qty';
const selectorLineAmount = '.refund_line_total';
const selectorTotalAmount = '#refund_amount';
const dataTable = [
	[ 'quantity', 'greater than maximum', selectorQty, '2' ],
	[ 'quantity', 'negative', selectorQty, '-1' ],
	[
		'refund amount in line item',
		'greater than maximum',
		selectorLineAmount,
		'100',
	],
	[ 'refund amount in line item', 'negative', selectorLineAmount, '-1' ],
	[
		'total refund amount',
		'greater than maximum',
		selectorTotalAmount,
		'100',
	],
	[ 'total refund amount', 'negative', selectorTotalAmount, '-1' ],
];

describe( 'Order > Refund Failure', () => {
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

		// Login
		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	describe.each( dataTable )(
		'Invalid %s',
		( fieldName, valueDescription, selector, value ) => {
			beforeEach( async () => {
				// Open the order
				await merchant.goToOrder( orderId );

				// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
				await page.removeAllListeners( 'dialog' );

				// Click the Refund button
				await expect( page ).toClick( 'button.refund-items' );

				// Verify the refund section shows
				await page.waitForSelector( 'div.wc-order-refund-items', {
					visible: true,
				} );

				// Verify Refund via WooCommerce Payments button is displayed
				await page.waitForSelector( 'button.do-api-refund' );
			} );

			it( `should fail refund attempt when ${ fieldName } is ${ valueDescription }`, async () => {
				// Initiate refund attempt
				await expect( page ).toFill( selector, value );

				await expect( page ).toMatchElement( '.do-api-refund', {
					text: /Refund .* via WooCommerce Payments/,
				} );

				// Confirm the refund
				const refundDialog = await expect( page ).toDisplayDialog(
					async () => {
						await expect( page ).toClick( 'button.do-api-refund' );
					}
				);

				// Confirm that the "Invalid refund amount" alert is shown, then close it
				const invalidRefundAlert = await expect( page ).toDisplayDialog(
					async () => {
						await refundDialog.accept();
						await uiUnblocked();
						await page.waitForNavigation( {
							waitUntil: 'networkidle0',
						} );
					}
				);
				await expect( invalidRefundAlert.message() ).toEqual(
					'Invalid refund amount'
				);
				await invalidRefundAlert.accept();

				// Verify that product line item does not show any refunds
				await expect( page ).not.toMatchElement(
					'.quantity .refunded'
				);
				await expect( page ).not.toMatchElement(
					'.line_cost .refunded'
				);

				// Verify that no entry is listed in the "Order refunds" section underneath the product line items
				await expect( page ).not.toMatchElement(
					'.refund > .line_cost'
				);

				// Verify that no system note for a refund was generated
				await expect( page ).not.toMatchElement( '.system-note', {
					text: 'refund',
				} );
			} );
		}
	);
} );
