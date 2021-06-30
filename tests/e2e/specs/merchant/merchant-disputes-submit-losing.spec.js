/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP } from '../../utils';
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
		await merchant.goToOrder( orderId );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process a losing dispute', async () => {
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
		await expect( page ).toMatchElement(
			'div.woocommerce-timeline-item__body a',
			{
				text: 'View dispute',
			}
		);

		// Get the link to the dispute details
		const disputeDetailsLink = await page.$eval(
			'div.woocommerce-timeline-item__body a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		// Open the dispute details
		await merchantWCP.openDisputeDetails( disputeDetailsLink );

		// Verify we're on the view dispute page
		await expect( page ).toMatchElement(
			'div.components-card > .components-card__header',
			{
				text: 'Dispute overview',
			}
		);
		await expect( page ).toMatchElement(
			'div.components-card > .components-card__header',
			{
				text: 'Dispute: Product not received',
			}
		);

		// Click to accept the dispute
		await merchantWCP.openAcceptDispute();
		await page.waitForSelector(
			'div.components-snackbar > .components-snackbar__content'
		);

		// Verify the dispute has been accepted properly
		await expect( page ).toMatchElement(
			'div.components-snackbar > .components-snackbar__content',
			{
				text:
					'You have accepted the dispute for order #' + orderId + '.',
			}
		);

		// Verify Lost status in disputes timeline
		await page.waitForSelector( 'span.chip-light' );
		await expect( page ).toMatchElement( 'span.chip-light', {
			text: 'Lost',
		} );
	} );

	it( 'should verify a dispute has been accepted properly', async () => {
		// Re-open the dispute to view the details
		await merchant.goToOrder( orderId );

		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);
		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Get the link to the dispute details
		const disputeDetailsLink = await page.$eval(
			'div.woocommerce-timeline-item__body a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		// Open the dispute details
		await merchantWCP.openDisputeDetails( disputeDetailsLink );

		// Check if buttons are not present anymore since a dispute has been accepted
		await expect( page ).not.toMatchElement(
			'div.components-card > .components-card__footer > a',
			{
				text: 'Challenge dispute',
			}
		);
		await expect( page ).not.toMatchElement(
			'div.components-card > .components-card__footer > button',
			{
				text: 'Accept dispute',
			}
		);
	} );
} );
