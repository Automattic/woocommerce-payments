/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { takeScreenshot } from '../../../utils/helpers';

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
		await expect( page ).toMatchTextContent( 'Order received' );

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

				await page
					.waitForSelector( '.woocommerce_order_items' )
					.then( ( el ) => el.scrollIntoView() );

				// Click the Refund button
				await expect( page ).toClick( 'button.refund-items' );

				takeScreenshot(
					`merchant-orders-refund-failures-before-timeout-${ fieldName }-${ valueDescription }`
				);

				// Verify the refund section shows
				await page.waitForSelector( 'div.wc-order-refund-items', {
					// visible: true,
					timeout: 5000,
				} );

				// Verify Refund via WooPayments button is displayed
				await page.waitForSelector( 'button.do-api-refund', {
					timeout: 5000,
				} );
			} );

			afterEach( () => {
				page.removeAllListeners( 'dialog' );
				page.on( 'dialog', async function ( dialog ) {
					try {
						await dialog.accept();
					} catch ( err ) {
						console.warn( err.message );
					}
				} );
			} );

			it( `should fail refund attempt when ${ fieldName } is ${ valueDescription }`, async () => {
				// Initiate refund attempt
				await expect( page ).toFill( selector, value, {
					timeout: 3000,
				} );

				await expect( page ).toMatchElement( '.do-api-refund', {
					text: /Refund .* via WooPayments/,
					timeout: 3000,
				} );

				// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
				page.removeAllListeners( 'dialog' );

				// Confirm the refund
				const refundDialog = await expect( page ).toDisplayDialog(
					async () => {
						await expect( page ).toClick( 'button.do-api-refund', {
							timeout: 3000,
						} );
					}
				);

				// Confirm that the "Invalid refund amount" alert is shown, then close it
				const invalidRefundAlert = await expect( page ).toDisplayDialog(
					async () => {
						await refundDialog.accept();
						await page.waitForTimeout( 1000 );
						// await page.waitForNavigation( {
						// 	waitUntil: 'networkidle0',
						// 	timeout: 5000,
						// } );
					}
				);
				await expect( invalidRefundAlert.message() ).toEqual(
					'Invalid refund amount'
				);
				await invalidRefundAlert.accept();

				// Verify that product line item does not show any refunds
				await expect( page ).not.toMatchElement(
					'.quantity .refunded',
					{ timeout: 3000 }
				);
				await expect( page ).not.toMatchElement(
					'.line_cost .refunded',
					{ timeout: 3000 }
				);

				// Verify that no entry is listed in the "Order refunds" section underneath the product line items
				await expect( page ).not.toMatchElement(
					'.refund > .line_cost',
					{ timeout: 3000 }
				);

				// Verify that no system note for a refund was generated
				await expect( page ).not.toMatchElement( '.system-note', {
					text: 'refund',
					timeout: 3000,
				} );
			} );
		}
	);
} );
