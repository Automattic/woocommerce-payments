/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { getMerchant, getShopper, isUIUnblocked } from '../../../utils/helpers';
import { placeOrderWithOptions } from '../../../utils/shopper';
import * as navigation from '../../../utils/merchant-navigation';

const orderStatusDropdownSelector = 'select[name="order_status"]';
const cancelModalSelector = 'div.wcpay-confirmation-modal';
const refundModalSelector = 'div.refund-confirmation-modal';
const refundCancelSelector =
	'.refund-confirmation-modal .wcpay-confirmation-modal__footer .is-secondary';
const refundConfirmSelector =
	'.refund-confirmation-modal .wcpay-confirmation-modal__footer .is-primary';
const selectedOrderStatusSelector = '.wc-order-status > span';
const orderPriceSelector =
	'#woocommerce-order-items .total .woocommerce-Price-amount';

const saveOrder = async ( page: Page ) => {
	await page.locator( '.save_order' ).click();
	await page.waitForLoadState( 'networkidle' );
};

const verifyOrderStatus = async ( page: Page, status: string ) => {
	const selectedOrderStatus = await page.$( selectedOrderStatusSelector );
	await expect(
		selectedOrderStatus.evaluate( ( el ) => el.textContent )
	).resolves.toBe( status );
};

test.describe( 'Order > Status Change', { tag: '@critical' }, () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let orderId: string;

	test.beforeAll( async ( { browser } ) => {
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		shopperPage = ( await getShopper( browser ) ).shopperPage;
	} );

	test.describe( 'Change Status of order to Cancelled', () => {
		test.beforeAll( async () => {
			orderId = await placeOrderWithOptions( shopperPage );
			await navigation.goToOrder( merchantPage, orderId );
		} );

		test( 'Show Cancel Confirmation modal, do not change status if Do Nothing selected', async () => {
			// Select cancel from the order status dropdown.
			await merchantPage.selectOption(
				orderStatusDropdownSelector,
				'Cancelled'
			);

			// Verify the confirmation modal shows.
			await merchantPage
				.locator( cancelModalSelector )
				.waitFor( { state: 'visible' } );

			// Click on Do Nothing.
			await merchantPage
				.getByRole( 'button', { name: 'Do Nothing' } )
				.click();

			// Verify the order status is set to processing.
			await verifyOrderStatus( merchantPage, 'Processing' );

			// Click on the update order button and wait for page reload.
			await saveOrder( merchantPage );

			// Verify the order status is set to processing.
			await verifyOrderStatus( merchantPage, 'Processing' );
		} );

		test( 'When Order Status changed to Cancel, show Cancel Confirmation modal, change status to Cancel if confirmed', async () => {
			// Select cancel from the order status dropdown.
			await merchantPage.selectOption(
				orderStatusDropdownSelector,
				'Cancelled'
			);

			// Verify the confirmation modal shows.
			await merchantPage
				.locator( cancelModalSelector )
				.waitFor( { state: 'visible' } );

			// Click on Cancel order.
			await merchantPage
				.getByRole( 'button', { name: 'Cancel order' } )
				.click();
			await merchantPage.waitForLoadState( 'networkidle' );

			// Verify the order status is set to cancel.
			await verifyOrderStatus( merchantPage, 'Cancelled' );

			// Click on the update order button and wait for page reload.
			await saveOrder( merchantPage );

			// Verify the order status is set to cancelled.
			await verifyOrderStatus( merchantPage, 'Cancelled' );
		} );
	} );

	test.describe( 'Change Status of order to Refunded', () => {
		test.beforeAll( async () => {
			orderId = await placeOrderWithOptions( shopperPage );
			await navigation.goToOrder( merchantPage, orderId );
		} );

		test( 'Show Refund Confirmation modal, do not change status if Cancel clicked', async () => {
			// Select refunded from the order status dropdown.
			await merchantPage.selectOption(
				orderStatusDropdownSelector,
				'Refunded'
			);

			// Verify the confirmation modal shows.
			await merchantPage
				.locator( refundModalSelector )
				.waitFor( { state: 'visible' } );

			// Click on Cancel.
			await merchantPage.locator( refundCancelSelector ).click();

			// Verify the order status is set to processing.
			await verifyOrderStatus( merchantPage, 'Processing' );

			// Click on the update order button and wait for page reload.
			await saveOrder( merchantPage );

			// Verify the order status is set to processing.
			await verifyOrderStatus( merchantPage, 'Processing' );
		} );

		test( 'Show Refund Confirmation modal, process Refund if confirmed', async () => {
			// Select refunded from the order status dropdown.
			await merchantPage.selectOption(
				orderStatusDropdownSelector,
				'Refunded'
			);

			// Verify the confirmation modal shows.
			await merchantPage
				.locator( refundModalSelector )
				.waitFor( { state: 'visible' } );

			// Click on Refund order.
			await merchantPage.locator( refundConfirmSelector ).click();

			// Wait for refund to be processed
			await isUIUnblocked( merchantPage );
			await merchantPage.waitForLoadState( 'networkidle' );

			// Get the order price
			const priceElement = await merchantPage.$( orderPriceSelector );
			const orderAmount = await merchantPage.evaluate(
				( el ) => el.textContent,
				priceElement
			);

			// Verify the refund amount is equal to the order amount.
			await expect(
				merchantPage.locator( '.refund > .line_cost' )
			).toHaveText( `-${ orderAmount }` );

			// Click on the update order button and wait for page reload.
			await saveOrder( merchantPage );

			// Verify the order status is set to refunded.
			await verifyOrderStatus( merchantPage, 'Refunded' );
		} );
	} );
} );
