// disputes save disputes for editing
/**
 * External dependencies
 */
import config from 'config';
/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

import { merchantWCP } from '../../../utils';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

let orderId;

describe( 'Disputes > Save dispute for editing', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );

		// Place an order with a dispute credit card
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.disputed-unreceived' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// Get the order ID so we can open it in the merchant view
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

	it( 'should show a dispute in payment details', async () => {
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
	} );

	it( 'should be able to save dispute for editing', async () => {
		// Get the link to the dispute details
		const disputeDetailsElement = await page.$(
			'[data-testid="view-dispute-button"]'
		);
		const disputeDetailsLink = await page.evaluate(
			( anchor ) => anchor.getAttribute( 'href' ),
			disputeDetailsElement
		);

		// Open the dispute details
		await merchantWCP.openDisputeDetails( disputeDetailsLink );

		// Click to challenge the dispute
		await merchantWCP.openChallengeDispute();

		await page.waitForSelector(
			'div.wcpay-dispute-evidence .components-flex.components-card__header',
			{
				timeout: 10000,
			}
		);

		// Verify we're on the challenge dispute page
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-evidence .components-flex.components-card__header',
			{
				text: 'Challenge dispute',
			}
		);

		await page.waitForSelector(
			'[data-testid="dispute-challenge-product-type-selector"]',
			{
				timeout: 10000,
			}
		);

		// Select the product type
		await expect( page ).toSelect(
			'[data-testid="dispute-challenge-product-type-selector"]',
			'offline_service'
		);

		await page.waitForSelector(
			'div.wcpay-dispute-evidence button.components-button.is-secondary',
			{
				timeout: 10000,
			}
		);

		await expect( page ).toClick(
			'div.wcpay-dispute-evidence button.components-button.is-secondary',
			{
				text: 'Save for later',
			}
		);

		// Re-open the dispute to view the details
		await merchantWCP.openDisputeDetails( disputeDetailsLink );

		// View the saved challenge
		await merchantWCP.openChallengeDispute();

		// Verify the previously selected Product type was saved
		await expect( page ).toMatchElement(
			'[data-testid="dispute-challenge-product-type-selector"]',
			{
				text: 'Offline service',
			}
		);
	} );
} );
