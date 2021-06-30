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

	it( 'should process a winning dispute', async () => {
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
				text: 'Dispute: Fraudulent',
			}
		);

		// Click to accept the dispute
		await merchantWCP.openChallengeDispute();

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

		// Fill required additional text in order to make a winning dispute
		// Used $eval as a workaround since .toFill won't work for this textarea
		await page.click( '#inspector-textarea-control-3' );
		await page.$eval(
			'#inspector-textarea-control-3',
			( el ) => ( el.value = 'winning_evidence' )
		);

		// Submit the evidence and accept the dialog
		await Promise.all( [
			page.removeAllListeners( 'dialog' ),
			evalAndClick(
				'div.components-card__footer > div > button.components-button.is-primary'
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

		// Verify Won status in disputes view
		await page.waitForSelector( 'span.chip-light' );
		await expect( page ).toMatchElement( 'span.chip-light', {
			text: 'Won',
		} );
	} );

	it( 'should verify a dispute has been challenged properly', async () => {
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

		// Check if a new button is present now
		const buttonText = await page.$eval(
			'div.components-card > div.components-flex > div > a',
			( el ) => el.innerText
		);
		await expect( page ).toMatch( buttonText, 'View submitted evidence' );
	} );
} );
