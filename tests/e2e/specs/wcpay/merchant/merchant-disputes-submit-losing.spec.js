/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper, evalAndClick } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { uiLoaded } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

let orderId;

describe.skip( 'Disputes > Submit losing dispute', () => {
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
		await merchant.goToOrder( orderId );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process and confirm a losing dispute', async () => {
		// Click the order dispute notice.
		await expect( page ).toClick( '[type="button"]', {
			text: 'Respond now',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		// Verify we see the dispute details on the transaction details page.
		await expect( page ).toMatchElement( '.dispute-notice', {
			text: 'The cardholder claims the product was not received',
		} );

		// Open the accept dispute modal.
		await evalAndClick( '[data-testid="open-accept-dispute-modal-button"' );
		await uiLoaded();
		// Click the accept dispute button.
		await evalAndClick( '[data-testid="accept-dispute-button"]' );
		// Wait for the accept POST request to resolve and the status chip to update with the new status.
		await expect( page ).toMatchElement( '.chip', {
			text: 'Disputed: Lost',
			timeout: 10000,
		} );

		// Check the dispute details footer
		await expect( page ).toMatchElement(
			'.transaction-details-dispute-footer *',
			{
				text: 'This dispute was accepted and lost',
			}
		);

		// Confirm buttons are not present anymore since a dispute has been accepted.
		await expect( page ).not.toMatchElement(
			'[data-testid="challenge-dispute-button"]'
		);
		await expect( page ).not.toMatchElement(
			'[data-testid="open-accept-dispute-modal-button"]'
		);
	} );
} );
