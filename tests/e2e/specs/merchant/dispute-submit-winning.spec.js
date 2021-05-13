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

describe( 'Disputes > Submit winning dispute', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );

		// Place an order to dispute later
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.disputed-fraudulent' );
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

	it( 'should process a winning dispute', async () => {
		await merchantWCP.openDisputes();
		await uiLoaded();

		// Verify a dispute is present with a proper ID and open it
		await page.waitForSelector( '.woocommerce-table__item > a', {
			text: orderId,
		} );
		await expect( page ).toMatchElement( '.woocommerce-table__item > a', {
			text: orderId,
		} );

		await uiLoaded();
		await page.waitForSelector( '.woocommerce-table__clickable-cell', {
			visible: true,
		} );
		await expect( page ).toClick( '.woocommerce-table__clickable-cell' );

		// Verify the heading for two component cards
		await page.waitForSelector( '.components-card__header', {
			visible: true,
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Dispute overview',
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Dispute: Fraudulent',
		} );

		// Challenge the dispute
		await expect( page ).toClick( 'a.components-button', {
			text: 'Challenge dispute',
		} );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();

		// Select product type
		await expect( page ).toSelect(
			'select#inspector-select-control-0',
			'physical_product'
		);

		// Verify the content blocks are present
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'General evidence',
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Shipping information',
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'Additional details',
		} );
		await expect( page ).toMatchElement( '.components-card__header', {
			text: 'General evidence',
		} );

		// Submit the evidence and accept the dialog
		await page.removeAllListeners( 'dialog' );
		const disputeDialog = await expect( page ).toDisplayDialog(
			async () => {
				await expect( page ).toClick( 'button.components-button', {
					text: 'Submit evidence',
				} );
			}
		);
		await disputeDialog.accept();
		await uiUnblocked();
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();
	} );
} );
