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
import { takeScreenshot } from '../../../utils';

const bnplProviders = [ [ 'Affirm' ], [ 'Afterpay' ] ];

const UPE_METHOD_CHECKBOXES = [
	"//label[contains(text(), 'Affirm')]/preceding-sibling::span/input[@type='checkbox']", // affirm
	"//label[contains(text(), 'Afterpay')]/preceding-sibling::span/input[@type='checkbox']", // afterpay
];

const cardTestingPreventionStates = [
	{ cardTestingPreventionEnabled: false },
	{ cardTestingPreventionEnabled: true },
];

// Skipping due to test failure – missing selector when changing account currency #8354
describe.each( cardTestingPreventionStates )(
	'BNPL checkout',
	( { cardTestingPreventionEnabled } ) => {
		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
			if ( cardTestingPreventionEnabled ) {
				await merchantWCP.enableCardTestingProtection();
			}
			await merchant.logout();
			await shopper.login();
		} );

		afterAll( async () => {
			await shopperWCP.emptyCart();
			await shopperWCP.logout();
			await merchant.login();
			await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
			if ( cardTestingPreventionEnabled ) {
				await merchantWCP.disableCardTestingProtection();
			}
			await merchant.logout();
		} );

		describe.each( bnplProviders )(
			`Checkout with %s, carding protection ${ cardTestingPreventionEnabled }`,
			( providerName ) => {
				it( `should successfully place order with ${ providerName }`, async () => {
					await shopperWCP.emptyCart();
					await setupProductCheckout(
						config.get( 'addresses.customer.billing' ),
						[ [ 'Beanie', 3 ] ]
					);
					await uiUnblocked();
					// Select BNPL provider as payment method.
					const xPathPaymentMethodSelector = `//*[@id='payment']/ul/li/label[contains(text(), '${ providerName }')]`;
					await page.waitForXPath( xPathPaymentMethodSelector );
					const [ paymentMethodLabel ] = await page.$x(
						xPathPaymentMethodSelector
					);
					await paymentMethodLabel.click();

					// Check the token presence when card testing prevention is enabled.
					if ( cardTestingPreventionEnabled ) {
						const token = await page.evaluate( () => {
							return window.wcpayFraudPreventionToken;
						} );
						expect( token ).not.toBeUndefined();
					}

					await page.waitFor( 4000 );
					await takeScreenshot(
						`shopper-bnpls-checkout-${ providerName }-payment-method-selected`
					);

					try {
						await shopper.placeOrder();
					} catch ( e ) {
						console.log( 'Error on shopper.placeOrder()', e );
					}

					await page.waitFor( 4000 );
					await takeScreenshot(
						`shopper-bnpls-checkout-${ providerName }-after-order-placed`
					);

					// Authorize payment with Stripe.
					// This XPath selector matches the Authorize Payment button, that is either a button or an anchor.
					try {
						const xPathAuthorizePaymentButton = `//*[self::button or self::a][contains(text(), 'Authorize Test Payment')]`;
						await page.waitForXPath( xPathAuthorizePaymentButton );
						const [ stripeButton ] = await page.$x(
							xPathAuthorizePaymentButton
						);
						await stripeButton.click();
					} catch ( error ) {
						console.error(
							'Error authorizing test payment:',
							error
						);
					}

					// Wait for the order confirmation page to load.
					await page.waitForNavigation( {
						waitUntil: 'networkidle0',
					} );
					await expect( page ).toMatch( 'Order received' );
				} );
			}
		);
	}
);
