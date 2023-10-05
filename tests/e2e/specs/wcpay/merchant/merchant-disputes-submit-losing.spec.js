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
		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Verify we have a dispute for this purchase
		await expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
			text: 'Payment disputed as Product not received.',
		} );

		// Accept the dispute
		await merchantWCP.openAcceptDispute();

		// If webhooks are not received, the dispute status won't be updated in the dispute list page resulting in test failure.
		// Workaround - Open payment details page again and check dispute's status.
		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Confirm buttons are not present anymore since a dispute has been accepted.
		await expect( page ).not.toMatchElement(
			// eslint-disable-next-line max-len
			'div.transaction-details-dispute-details-body div.transaction-details-dispute-details-body__actions button.components-button.is-primary',
			{
				text: 'Challenge dispute',
			}
		);
		await expect( page ).not.toMatchElement(
			// eslint-disable-next-line max-len
			'div.transaction-details-dispute-details-body div.transaction-details-dispute-details-body__actions button.components-button.is-tertiary',
			{
				text: 'Accept dispute',
			}
		);

		// Confirm dispute status is Lost.
		await page.waitForSelector( 'li.woocommerce-timeline-item' );
		await expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
			text: 'Dispute lost. The bank ruled in favor of your customer.',
		} );
	} );
} );
