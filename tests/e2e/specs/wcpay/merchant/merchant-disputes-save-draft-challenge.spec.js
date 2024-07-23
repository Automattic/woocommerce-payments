// disputes save disputes for editing
/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper, evalAndClick } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';
import { uiLoaded } from '../../../utils';

let orderId;

describe( 'Disputes > Merchant can save and resume draft dispute challenge', () => {
	beforeAll( async () => {
		await page.goto( config.get( 'url' ), { waitUntil: 'networkidle0' } );

		// Place an order with a dispute credit card
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.disputed-unreceived' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatchTextContent( 'Order received' );

		// Get the order ID so we can open it in the merchant view
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		await merchant.login();
		await merchant.goToOrder( orderId );

		// Get the payment details link from the order page.
		const paymentDetailsLink = await page.$eval(
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
			text: 'The cardholder claims the product was not received',
		} );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should be able to save a draft dispute challenge and resume', async () => {
		// Click the challenge dispute button.
		await evalAndClick( '[data-testid="challenge-dispute-button"]' );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await uiLoaded();

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

		// Reload the page
		await page.reload();

		await uiLoaded();

		// Verify the previously selected Product type was saved
		await expect( page ).toMatchElement(
			'[data-testid="dispute-challenge-product-type-selector"]',
			{
				text: 'Offline service',
			}
		);
	} );
} );
