/**
 * External dependencies
 */
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );
import config from 'config';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { setupProductCheckout } from '../../../utils/payments';

const bnplProviders = [ [ 'Affirm' ], [ 'Afterpay' ] ];

const UPE_METHOD_CHECKBOXES = [
	'#inspector-checkbox-control-3', // affirm
	'#inspector-checkbox-control-4', // afterpay
];

describe( 'BNPL checkout', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.activateUPEWithDefferedIntentCreation();
		await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
	} );

	afterAll( async () => {
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchantWCP.deactivateUPEWithDefferedIntentCreation();
		await merchant.logout();
	} );

	describe.each( bnplProviders )( 'Checkout with %s', ( providerName ) => {
		beforeEach( async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' ),
				[ [ 'Beanie', 3 ] ]
			);
		} );

		it( `should successfully place order with ${ providerName }`, async () => {
			await uiUnblocked();
			// Select BNPL provider as payment method.
			const xPathPaymentMethodSelector = `//*[@id='payment']/ul/li/label[contains(text(), '${ providerName }')]`;
			await page.waitForXPath( xPathPaymentMethodSelector );
			const [ paymentMethodLabel ] = await page.$x(
				xPathPaymentMethodSelector
			);
			await paymentMethodLabel.click();
			await shopper.placeOrder();

			// Authorize payment with Stripe.
			// This XPath selector matches the Authorize Payment button, that is either a button or an anchor.
			const xPathAuthorizePaymentButton = `//*[self::button or self::a][contains(text(), 'Authorize Test Payment')]`;
			await page.waitForXPath( xPathAuthorizePaymentButton );
			const [ stripeButton ] = await page.$x(
				xPathAuthorizePaymentButton
			);
			await stripeButton.click();

			// Wait for the order confirmation page to load.
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	} );
} );
