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
	'#inspector-checkbox-control-5', // klarna
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
		// await shopperWCP.changeAccountCurrencyTo( 'USD' );
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
				await uiUnblocked();
				// Select BNPL provider as paymnent method.
				await page.waitForSelector( paymentMethodSelector );
				await expect( page ).toClick( paymentMethodSelector );
				await shopper.placeOrder();
				// Authorize payment with Stripe.
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

	describe( 'Klarna', () => {
		it( 'should checkout', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' ),
				[ [ 'Beanie', 3 ] ]
			);
			await uiUnblocked();
			// Select BNPL provider as payment method and place order.
			const klarnaPaymentMethod = await page.waitForSelector(
				'li.payment_method_woocommerce_payments_klarna'
			);
			await klarnaPaymentMethod.click();
			await shopper.placeOrder();

			// Authorize payment with Klarna.
			// Everything happens in an iframe. We need to switch to it.
			const frameHandle = await page.waitForSelector(
				'#klarna-apf-iframe'
			);
			const klarnaFrame = await frameHandle.contentFrame();

			const continueButton = await klarnaFrame.waitForSelector(
				'button[data-testid="kaf-button"]'
			);
			await continueButton.click();

			const otpInput = await klarnaFrame.waitForSelector( '#otp_field' );
			await otpInput.type( '123456', { delay: 20 } );

			// const pickPlanButton = await klarnaFrame.waitForSelector(
			// 	'button[data-testid="pick-plan"]'
			// );

			// if ( pickPlanButton ) {
			// 	await pickPlanButton.click();
			// }

			// const selectPaymentCategoryButton = await klarnaFrame.waitForSelector(
			// 	'button[data-testid="select-payment-category"]'
			// );

			// if ( selectPaymentCategoryButton ) {
			// 	await selectPaymentCategoryButton.click();
			// }

			const buyButton = await klarnaFrame.waitForSelector(
				'#buy_button'
			);
			await buyButton.click();

			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );
	} );
} );
