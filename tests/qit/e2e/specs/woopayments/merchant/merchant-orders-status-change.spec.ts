/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { placeOrderWithOptions } from '../../../utils/shopper';
import { goToOrder } from '../../../utils/merchant';
import { isUIUnblocked } from '../../../utils/helpers';

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

const saveOrder = async ( page ) => {
	await page.locator( '.save_order' ).click();
	await page.waitForLoadState( 'networkidle' );
};

const verifyOrderStatus = async ( page, status: string ) => {
	const selectedOrderStatus = await page.$( selectedOrderStatusSelector );
	await expect(
		selectedOrderStatus.evaluate( ( el ) => el.textContent )
	).resolves.toBe( status );
};

test.describe(
	'Order > Status Change',
	{ tag: [ '@merchant', '@critical' ] },
	() => {
		let orderId: string;

		test.describe( 'Change Status of order to Cancelled', () => {
			test.beforeAll( async ( { customerPage } ) => {
				orderId = await placeOrderWithOptions( customerPage );
			} );

			test( 'Show Cancel Confirmation modal, do not change status if Do Nothing selected', async ( {
				adminPage,
			} ) => {
				await goToOrder( adminPage, orderId );

				// Select cancel from the order status dropdown.
				await adminPage.selectOption(
					orderStatusDropdownSelector,
					'Cancelled'
				);

				// Verify the confirmation modal shows.
				await adminPage
					.locator( cancelModalSelector )
					.waitFor( { state: 'visible' } );

				// Click on Do Nothing.
				await adminPage
					.getByRole( 'button', { name: 'Do Nothing' } )
					.click();

				// Verify the order status is set to processing.
				await verifyOrderStatus( adminPage, 'Processing' );

				// Click on the update order button and wait for page reload.
				await saveOrder( adminPage );

				// Verify the order status is set to processing.
				await verifyOrderStatus( adminPage, 'Processing' );
			} );

			test( 'When Order Status changed to Cancel, show Cancel Confirmation modal, change status to Cancel if confirmed', async ( {
				adminPage,
			} ) => {
				await goToOrder( adminPage, orderId );

				// Select cancel from the order status dropdown.
				await adminPage.selectOption(
					orderStatusDropdownSelector,
					'Cancelled'
				);

				// Verify the confirmation modal shows.
				await adminPage
					.locator( cancelModalSelector )
					.waitFor( { state: 'visible' } );

				// Click on Cancel order.
				await adminPage
					.getByRole( 'button', { name: 'Cancel order' } )
					.click();
				await adminPage.waitForLoadState( 'networkidle' );

				// Verify the order status is set to cancel.
				await verifyOrderStatus( adminPage, 'Cancelled' );

				// Click on the update order button and wait for page reload.
				await saveOrder( adminPage );

				// Verify the order status is set to cancelled.
				await verifyOrderStatus( adminPage, 'Cancelled' );
			} );
		} );

		test.describe( 'Change Status of order to Refunded', () => {
			test.beforeAll( async ( { customerPage } ) => {
				orderId = await placeOrderWithOptions( customerPage );
			} );

			test( 'Show Refund Confirmation modal, do not change status if Cancel clicked', async ( {
				adminPage,
			} ) => {
				await goToOrder( adminPage, orderId );

				// Select refunded from the order status dropdown.
				await adminPage.selectOption(
					orderStatusDropdownSelector,
					'Refunded'
				);

				// Verify the confirmation modal shows.
				await adminPage
					.locator( refundModalSelector )
					.waitFor( { state: 'visible' } );

				// Click on Cancel.
				await adminPage.locator( refundCancelSelector ).click();

				// Verify the order status is set to processing.
				await verifyOrderStatus( adminPage, 'Processing' );

				// Click on the update order button and wait for page reload.
				await saveOrder( adminPage );

				// Verify the order status is set to processing.
				await verifyOrderStatus( adminPage, 'Processing' );
			} );

			test( 'Show Refund Confirmation modal, process Refund if confirmed', async ( {
				adminPage,
			} ) => {
				await goToOrder( adminPage, orderId );

				// Select refunded from the order status dropdown.
				await adminPage.selectOption(
					orderStatusDropdownSelector,
					'Refunded'
				);

				// Verify the confirmation modal shows.
				await adminPage
					.locator( refundModalSelector )
					.waitFor( { state: 'visible' } );

				// Click on Refund order.
				await adminPage.locator( refundConfirmSelector ).click();

				// Wait for refund to be processed
				await isUIUnblocked( adminPage );
				await adminPage.waitForLoadState( 'networkidle' );

				// Get the order price
				const priceElement = await adminPage.$( orderPriceSelector );
				const orderAmount = await adminPage.evaluate(
					( el ) => el.textContent,
					priceElement
				);

				// Verify the refund amount is equal to the order amount.
				await expect(
					adminPage.locator( '.refund > .line_cost' )
				).toHaveText( `-${ orderAmount }` );

				// Click on the update order button and wait for page reload.
				await saveOrder( adminPage );

				// Verify the order status is set to refunded.
				await verifyOrderStatus( adminPage, 'Refunded' );
			} );
		} );
	}
);
