/**
 * External dependencies
 */
import test, { Dialog, Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { emptyCart, placeOrderWithCurrency } from '../../../utils/shopper';
import { getMerchant, getShopper } from '../../../utils/helpers';
import { goToOrder } from '../../../utils/merchant-navigation';
import { ensureOrderIsProcessed } from '../../../utils/merchant';

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

test.describe( 'Order > Refund Failure', () => {
	test.beforeAll( async ( { browser } ) => {
		const { shopperPage } = await getShopper( browser );
		const { merchantPage } = await getMerchant( browser );

		// Place an order to refund later
		await emptyCart( shopperPage );
		orderId = await placeOrderWithCurrency( shopperPage, 'USD' );
		await ensureOrderIsProcessed( merchantPage, orderId );
	} );

	dataTable.forEach( ( [ fieldName, valueDescription, selector, value ] ) => {
		test.describe( 'Invalid ' + fieldName, () => {
			let merchantPage: Page;
			test.beforeEach( async ( { browser } ) => {
				merchantPage = ( await getMerchant( browser ) ).merchantPage;
				// Open the order
				await goToOrder( merchantPage, orderId );

				// We need to remove any listeners on the `dialog` event otherwise we can't catch the dialog below
				browser.removeAllListeners( 'dialog' );

				// Sometimes the element is not clickable due to the header getting on the way. This seems to
				// only happen in CI for WC 7.7.0 so the workaround is to remove those elements.
				const hideElementIfExists = ( selectorToHide: string ) => {
					const element = document.querySelector( selectorToHide );
					if ( element ) {
						element.outerHTML = '';
					}
				};
				await merchantPage.evaluate(
					hideElementIfExists,
					'.woocommerce-layout__header'
				);
				await merchantPage.evaluate(
					hideElementIfExists,
					'#wpadminbar'
				);

				// Click the Refund button
				const refundItemsButton = merchantPage
					.getByRole( 'button', {
						name: 'Refund',
					} )
					.first();
				await refundItemsButton.click();

				// Verify the refund section shows
				await merchantPage.waitForSelector(
					'div.wc-order-refund-items'
				);

				// Verify Refund via WooPayments button is displayed
				await merchantPage.waitForSelector( 'button.do-api-refund' );
			} );

			test(
				`should fail refund attempt when ${ fieldName } is ${ valueDescription }`,
				{ tag: '@critical' },
				async () => {
					// Initiate refund attempt
					await merchantPage
						.locator( selector )
						.first()
						.fill( value );

					const refundButton = await merchantPage.waitForSelector(
						'.do-api-refund',
						{
							state: 'visible',
						}
					);
					const refundButtonText: string = await refundButton.textContent();
					expect( refundButtonText ).toMatch(
						/Refund .* via WooPayments.+/
					);

					function* dialogHandler( dialog: Dialog ) {
						yield dialog.accept();
						expect( dialog.message() ).toBe(
							'Invalid refund amount'
						);
						yield dialog.accept();
					}

					// Confirm the refund. There will be two dialogs shown, one for the refund
					// confirmation, one for the error message. Accept the first one, and
					// verify the second one.
					merchantPage.on(
						'dialog',
						( dialog ) => dialogHandler( dialog ).next().value
					);

					// The above will happen once we click the refund button.
					await refundButton.click();

					// Verify that no entry is listed in the "Order refunds" section underneath the product line items
					await expect(
						merchantPage.locator( '#order_refunds' )
					).not.toBeVisible();
				}
			);
		} );
	} );
} );
