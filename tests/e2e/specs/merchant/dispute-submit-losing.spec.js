/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
	uiUnblocked,
	evalAndClick,
} = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP } from '../../utils/flows';
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

		// Get the order ID so we can verify it in the disputes listing
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
		await merchantWCP.openDisputes();

		// Verify a dispute is present with a proper ID and open it
		await expect( page ).toMatchElement( '.woocommerce-table__item > a', {
			text: orderId,
		} );
		await page.waitForSelector( '.woocommerce-table__item > a', {
			visible: true,
			timeout: 1000,
		} );
		await evalAndClick( '.woocommerce-table__item > a' );

		// Verify the heading for two component cards
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

		// Verify the dispute has been accepted properly
		await expect( page ).toMatchElement(
			'span.chip.chip-light.is-compact',
			{
				text: 'Lost',
			}
		);
		await expect( page ).toClick( '.woocommerce-table__item > a' );
		await expect( page ).not.toMatchElement( 'button.components-button', {
			text: 'Challenge dispute',
		} );
		await expect( page ).not.toMatchElement( 'button.components-button', {
			text: 'Accept dispute',
		} );
	} );
} );
