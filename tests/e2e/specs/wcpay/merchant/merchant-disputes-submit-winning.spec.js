/**
 * External dependencies
 */
import config from 'config';
/**
 * Internal dependencies
 */
import { merchantWCP, uiLoaded } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const {
	merchant,
	shopper,
	evalAndClick,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );

let orderId;

describe( 'Disputes > Submit winning dispute', () => {
	let paymentDetailsLink;

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

		// Get the order ID
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		await merchant.login();
		await merchant.goToOrder( orderId );

		// Get the payment details link from the order page.
		paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);

		// Open the payment details page and wait for it to load.
		await Promise.all( [
			page.goto( paymentDetailsLink, {
				waitUntil: 'networkidle0',
			} ),
			uiLoaded(),
		] );

		// Verify we see the dispute details on the transaction details page.
		await expect( page ).toMatchElement( '.dispute-notice', {
			text: 'The cardholder claims this is an unauthorized transaction',
		} );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should process and confirm a winning dispute', async () => {
		// Click the challenge dispute button.
		await evalAndClick( '[data-testid="challenge-dispute-button"]' );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();

		// Select product type
		await expect( page ).toSelect(
			'[data-testid="dispute-challenge-product-type-selector"]',
			'physical_product'
		);

		// Verify the content blocks are present
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-evidence .components-card__header',
			{
				text: 'General evidence',
			}
		);
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-evidence .components-card__header',
			{
				text: 'Shipping information',
			}
		);
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-evidence .components-card__header',
			{
				text: 'Additional details',
			}
		);

		// Fill Additional Details field with required text in order to win dispute
		await expect(
			page
		).toFill(
			'div.wcpay-dispute-evidence #inspector-textarea-control-3',
			'winning_evidence',
			{ delay: 20 }
		);

		// Submit the evidence and accept the dialog
		await Promise.all( [
			page.removeAllListeners( 'dialog' ),
			evalAndClick(
				'div.wcpay-dispute-evidence .components-card__footer > div > button.components-button.is-primary'
			),
			page.on( 'dialog', async ( dialog ) => {
				await dialog.accept();
			} ),
			uiUnblocked(),
			page.waitForNavigation( { waitUntil: 'networkidle0' } ),
			uiLoaded(),
		] );

		// If webhooks are not received, the dispute status won't be updated in the dispute list page resulting in test failure.
		// Workaround - Open payment details page again and check dispute's status.
		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Confirm dispute status is Won.
		await page.waitForSelector( 'li.woocommerce-timeline-item' );
		await expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
			text: 'Dispute won! The bank ruled in your favor.',
		} );
	} );
} );
