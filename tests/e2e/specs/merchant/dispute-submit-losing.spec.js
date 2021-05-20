/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, uiLoaded } from '../../utils';
import { fillCardDetails, setupProductCheckout } from '../../utils/payments';

let orderId;

describe( 'Disputes > Submit losing dispute', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );

		// Place an order to dispute later
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.disputed-unreceived' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// Get the order ID
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		await merchant.login();
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process a losing dispute', async () => {
		// Go to dispute page
		await merchant.openAllOrdersView();
		await merchant.goToOrder( orderId );
		await merchantWCP.viewDisputeFromOrder();

		// Verify the heading for two component cards
		await page.waitForSelector( '.components-card__header', {
			visible: true,
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Dispute overview',
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Dispute: Product not received',
		} );

		// Accept the dispute
		await page.removeAllListeners( 'dialog' );
		const disputeDialog = await expect( page ).toDisplayDialog(
			async () => {
				await expect( page ).toClick( 'button.components-button', {
					text: 'Accept dispute',
				} );
			}
		);
		await disputeDialog.accept();
		await uiUnblocked();
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();

		// Verify the dispute has been accepted properly
		await expect( page ).toMatchElement(
			'div.components-snackbar > .components-snackbar__content',
			{
				text:
					'You have accepted the dispute for order #' + orderId + '.',
			}
		);
		await merchant.openAllOrdersView();
		await merchant.goToOrder( orderId );
		await merchantWCP.viewDisputeFromOrder();
		await expect( page ).not.toMatchElement( 'button.components-button', {
			text: 'Challenge dispute',
		} );
		await expect( page ).not.toMatchElement( 'button.components-button', {
			text: 'Accept dispute',
		} );
	} );
} );
