/**
 * External dependencies
 */
import { Dialog, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test } from '../../../fixtures/auth';
import { emptyCart, placeOrderWithCurrency } from '../../../utils/shopper';
import { goToOrder, ensureOrderIsProcessed } from '../../../utils/merchant';

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
let orderId: string;

test.describe( 'Order > Refund Failure', { tag: '@merchant' }, () => {
	test.beforeAll( async ( { customerPage, adminPage } ) => {
		// Place an order to refund later
		await emptyCart( customerPage );
		orderId = await placeOrderWithCurrency( customerPage, 'USD' );
		await ensureOrderIsProcessed( adminPage );
	} );

	dataTable.forEach( ( [ fieldName, valueDescription, selector, value ] ) => {
		test.describe( 'Invalid ' + fieldName, () => {
			test.beforeEach( async ( { adminPage } ) => {
				// Open the order
				await goToOrder( adminPage, orderId );

				// Sometimes the element is not clickable due to the header getting on the way. This seems to
				// only happen in CI for WC 7.7.0 so the workaround is to remove those elements.
				const hideElementIfExists = ( selectorToHide: string ) => {
					const element = document.querySelector( selectorToHide );
					if ( element ) {
						element.outerHTML = '';
					}
				};
				await adminPage.evaluate(
					hideElementIfExists,
					'.woocommerce-layout__header'
				);
				await adminPage.evaluate( hideElementIfExists, '#wpadminbar' );

				// Click the Refund button
				const refundItemsButton = adminPage
					.getByRole( 'button', {
						name: 'Refund',
					} )
					.first();
				await refundItemsButton.click();

				// Verify the refund section shows
				await adminPage.waitForSelector( 'div.wc-order-refund-items' );

				// Verify Refund via WooPayments button is displayed
				await adminPage.waitForSelector( 'button.do-api-refund' );
			} );

			test(
				`should fail refund attempt when ${ fieldName } is ${ valueDescription }`,
				{ tag: '@critical' },
				async ( { adminPage } ) => {
					// Initiate refund attempt
					await adminPage.locator( selector ).first().fill( value );

					const refundButton = await adminPage.waitForSelector(
						'.do-api-refund',
						{
							state: 'visible',
						}
					);
					const refundButtonText: string = await refundButton.textContent();
					expect( refundButtonText ).toMatch(
						/Refund .* via WooPayments.+/
					);

					const dialogMessages: string[] = [];

					// Handle dialogs - accept all and capture messages
					adminPage.on( 'dialog', async ( dialog: Dialog ) => {
						dialogMessages.push( dialog.message() );
						await dialog.accept();
					} );

					// Click the refund button - this may or may not trigger dialogs
					// depending on the type of invalid input (client vs server validation)
					await refundButton.click();

					// Wait for any dialogs or validation to be processed
					await adminPage.waitForTimeout( 2000 );

					// Verify that no entry is listed in the "Order refunds" section underneath the product line items
					await expect(
						adminPage.locator( '#order_refunds' )
					).not.toBeVisible();
				}
			);
		} );
	} );
} );
