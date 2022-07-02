/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
	evalAndClick,
	uiUnblocked,
} = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, uiLoaded } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

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

	it( 'should process and confirm a winning dispute', async () => {
		// Pull out and follow the link to avoid working in multiple tabs
		const paymentDetailsLink = await page.$eval(
			'p.order_number > a',
			( anchor ) => anchor.getAttribute( 'href' )
		);
		await merchantWCP.openPaymentDetails( paymentDetailsLink );

		// Verify we have a dispute for this purchase
		await expect( page ).toMatchElement( 'li.woocommerce-timeline-item', {
			text: 'Payment disputed as Fraudulent.',
		} );
		await expect( page ).toMatchElement(
			'div.woocommerce-timeline-item__body a',
			{
				text: 'View dispute',
			}
		);

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

		// Verify we're on the view dispute page
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-details .header-dispute-overview',
			{
				text: 'Dispute overview',
			}
		);
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-details .components-card > .components-card__header',
			{
				text: 'Dispute: Fraudulent',
			}
		);

		// Challenge the dispute
		await merchantWCP.openChallengeDispute();

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
		await page.waitForSelector(
			'div.components-snackbar > .components-snackbar__content'
		);

		// Verify the dispute has been challenged properly
		await expect( page ).toMatchElement(
			'div.components-snackbar > .components-snackbar__content',
			{
				text: 'Evidence submitted!',
			}
		);

		// If webhooks are not received, the dispute status won't be updated in the dispute list page resulting in test failure.
		// Workaround - Open dispute details page again and check status.
		await merchantWCP.openDisputeDetails( disputeDetailsLink );
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-details .header-dispute-overview',
			{
				text: 'Dispute overview',
			}
		);

		// Check view submitted evidence is present on page.
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-details .components-card > div.components-flex > div > a',
			{
				text: 'View submitted evidence',
			}
		);

		// Confirm dispute status is Won.
		await page.waitForSelector(
			'div.wcpay-dispute-details .header-dispute-overview span.chip-light'
		);
		await expect( page ).toMatchElement(
			'div.wcpay-dispute-details .header-dispute-overview span.chip-light',
			{
				text: 'Won',
			}
		);
	} );
} );
