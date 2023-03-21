/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const orderIdSelector = '.woocommerce-order-overview__order.order > strong';
const orderStatusDropdownSelector = 'select[name="order_status"]';
const cancelModalSelector = 'div.cancel-confirmation-modal';
const refundModalSelector = 'div.refund-confirmation-modal';
const refundCancelSelector =
	'.refund-confirmation-modal .wcpay-confirmation-modal__footer .is-secondary';
const refundConfirmSelector =
	'.refund-confirmation-modal .wcpay-confirmation-modal__footer .is-primary';
const selectedOrderStatusSelector = '.wc-order-status > span';

let orderId;

describe( 'Order > Status Change', () => {
	describe( 'Change Status of order to Cancelled', () => {
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
			const orderIdField = await page.$( orderIdSelector );
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			// Login and open the order
			await merchant.login();
			await merchant.goToOrder( orderId );
		} );

		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'Show Cancel Confirmation modal, do not change status if Do Nothing selected', async () => {
			// Select cancel from the order status dropdown.
			await expect( page ).toSelect(
				orderStatusDropdownSelector,
				'Cancelled'
			);

			// Verify the confirmation modal shows.
			await page.waitForSelector( cancelModalSelector, {
				visible: true,
			} );

			// Click on Do Nothing.
			await expect( page ).toClick( 'button', { text: 'Do Nothing' } );

			// Verify the order status is set to processing.
			const selectedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				selectedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Processing' );

			//click Update Order button and wait for page reloaded.
			await expect( page ).toClick( '.save_order' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			// Verify the order status is set to processing.
			const updatedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				updatedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Processing' );
		} );

		it( 'When Order Status changed to Cancel, show Cancel Confirmation modal, change status to Cancel if confirmed', async () => {
			// Select cancel from the order status dropdown.
			await expect( page ).toSelect(
				orderStatusDropdownSelector,
				'Cancelled'
			);

			// Verify the confirmation modal shows.
			await page.waitForSelector( cancelModalSelector, {
				visible: true,
			} );

			// Click on Cancel order.
			await expect( page ).toClick( 'button', { text: 'Cancel order' } );

			// Verify the order status is set to cancel.
			const selectedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				selectedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Cancelled' );

			//click Update Order button.
			await expect( page ).toClick( '.save_order' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			// Verify the order status is set to processing.
			const updatedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				updatedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Cancelled' );
		} );
	} );

	describe( 'Change Status of order to Refunded', () => {
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
			const orderIdField = await page.$( orderIdSelector );
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			// Login and open the order
			await merchant.login();
			await merchant.goToOrder( orderId );
		} );

		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'Show Refund Confirmation modal, do not change status if Cancel clicked', async () => {
			// Select refunded from the order status dropdown.
			await expect( page ).toSelect(
				orderStatusDropdownSelector,
				'Refunded'
			);

			// Verify the confirmation modal shows.
			await page.waitForSelector( refundModalSelector, {
				visible: true,
			} );

			// Click on Cancel.
			await expect( page ).toClick( refundCancelSelector );

			// Verify the order status is set to processing.
			const selectedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				selectedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Processing' );

			//click Update Order button and wait for page reloaded.
			await expect( page ).toClick( '.save_order' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			// Verify the order status is set to processing.
			const updatedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				updatedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Processing' );
		} );

		it( 'Show Refund Confirmation modal, process Refund if confirmed', async () => {
			// Select refunded from the order status dropdown.
			await expect( page ).toSelect(
				orderStatusDropdownSelector,
				'Refunded'
			);

			// Verify the confirmation modal shows.
			await page.waitForSelector( refundModalSelector, {
				visible: true,
			} );

			// Click on Refund order.
			await expect( page ).toClick( refundConfirmSelector );

			// Wait for refund to be processed
			await uiUnblocked();
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Get the order price
			const priceElement = await page.$(
				'#woocommerce-order-items .total .woocommerce-Price-amount'
			);
			const orderAmount = await page.evaluate(
				( el ) => el.textContent,
				priceElement
			);

			//Verify the refund amount is equal to the order amount.
			expect( page ).toMatchElement( '.refund > .line_cost', {
				text: `-${ orderAmount }`,
			} );

			//click Update Order button.
			await expect( page ).toClick( '.save_order' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			// Verify the order status is set to refunded.
			const updatedOrderStatus = await page.$(
				selectedOrderStatusSelector
			);
			await expect(
				updatedOrderStatus.evaluate( ( el ) => el.innerText )
			).resolves.toBe( 'Refunded' );
		} );
	} );
} );
