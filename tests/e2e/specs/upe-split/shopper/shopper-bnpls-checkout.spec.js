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

const bnplProviders = [
	[ 'Affirm', 'li.payment_method_woocommerce_payments_affirm', 'button' ],
	[
		'Afterpay/Clearpay',
		'li.payment_method_woocommerce_payments_afterpay_clearpay',
		'a',
	],
];

const UPE_METHOD_CHECKBOXES = [
	'#inspector-checkbox-control-3', // affirm
	'#inspector-checkbox-control-4', // afterpay
];

const STRIPE_AUTHORIZE_PAYMENT_BUTTON_SELECTOR =
	'.common-Button.common-Button--default[name="success"]';

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

	describe.each( bnplProviders )(
		'Checkout with %s',
		( providerName, paymentMethodSelector, stripeButtonHTMLElement ) => {
			beforeEach( async () => {
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ 'Beanie', 3 ] ]
				);
			} );

			it( `should successfully place order with ${ providerName }`, async () => {
				await page.waitForSelector( paymentMethodSelector );
				await expect( page ).toClick( paymentMethodSelector );
				await uiUnblocked();
				await shopper.placeOrder();
				await page.waitForSelector(
					STRIPE_AUTHORIZE_PAYMENT_BUTTON_SELECTOR
				);
				await expect( page ).toClick( stripeButtonHTMLElement, {
					text: 'Authorize Test Payment',
				} );
				await page.waitForNavigation( {
					waitUntil: 'networkidle0',
				} );
				await expect( page ).toMatch( 'Order received' );
			} );
		}
	);
} );
