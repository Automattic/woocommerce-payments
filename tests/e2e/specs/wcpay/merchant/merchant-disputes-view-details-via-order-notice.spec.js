/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

describe( 'Disputes > View dispute details via disputed order notice', () => {
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
		const orderId = await orderIdField.evaluate( ( el ) => el.innerText );

		await merchant.login();
		await merchant.goToOrder( orderId );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should navigate to dispute details when disputed order notice button clicked', async () => {
		// If WC < 7.9, return early since the order dispute notice is not present.
		const orderPaymentDetailsContainer = await page.$(
			'#wcpay-order-payment-details-container'
		);
		if ( ! orderPaymentDetailsContainer ) {
			// eslint-disable-next-line no-console
			console.log(
				'Skipping test since the order dispute notice is not present in WC < 7.9'
			);
			return;
		}

		// Click the order dispute notice.
		await expect( page ).toClick( '[type="button"]', {
			text: 'Respond now',
		} );

		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		// Verify we see the dispute details on the transaction details page.
		await expect( page ).toMatchElement( '.dispute-notice', {
			text: 'The cardholder claims this is an unauthorized transaction',
		} );
	} );
} );
