/**
 * External dependencies
 */
import config from 'config';
/**
 * Internal dependencies
 */
import { merchantWCP } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

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
		await merchant.goToOrder( orderId );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process and confirm a losing dispute', async () => {
		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Verify we have a dispute for this purchase
		await expect( page ).toMatchElement( '.dispute-notice', {
			text: 'The cardholder claims the product was not received',
		} );

		// Accept the dispute
		await merchantWCP.openAcceptDispute();

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
