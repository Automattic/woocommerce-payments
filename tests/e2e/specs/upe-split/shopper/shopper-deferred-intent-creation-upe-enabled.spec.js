/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import {
	confirmCardAuthentication,
	fillCardDetails,
	setupProductCheckout,
} from '../../../utils/payments';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const UPE_METHOD_CHECKBOXES = [
	'#inspector-checkbox-control-3', // affirm
	'#inspector-checkbox-control-4', // afterpay
	'#inspector-checkbox-control-5', // bancontact
	'#inspector-checkbox-control-6', // eps
	'#inspector-checkbox-control-7', // giropay
	'#inspector-checkbox-control-8', // ideal
	'#inspector-checkbox-control-9', // sofort
	'#inspector-checkbox-control-10', // p24
];
const card = config.get( 'cards.basic' );
const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 20000;
const STRIPE_AUTHORIZE_PAYMENT_BUTTON_SELECTOR =
	'.common-Button.common-Button--default[name="success"]';

describe( 'Enabled UPE with deferred intent creation', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.activateUPEWithDefferedIntentCreation();
		await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
	} );

	afterAll( async () => {
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
		await shopperWCP.emptyCart();
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchantWCP.deactivateUPEWithDefferedIntentCreation();
		await merchant.logout();
	} );

	describe( 'Enabled UPE with deferred intent creation', () => {
		it( 'should successfully place order with the default card', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await fillCardDetails( page, card );
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'should process a payment with authentication for the 3DS card', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await fillCardDetails( page, config.get( 'cards.3ds' ) );
			await shopper.placeOrder();
			await confirmCardAuthentication( page, '3DS' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'should successfully save the card', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await fillCardDetails( page, card );
			await shopperWCP.toggleSavePaymentMethod();
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );

			// validate that the payment method has been added to the customer.
			await shopperWCP.goToPaymentMethods();
			await expect( page ).toMatch( card.label );
			await expect( page ).toMatch(
				`${ card.expires.month }/${ card.expires.year }`
			);
		} );

		it( 'should process a payment with the saved card', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await shopper.goToCheckout();
			await uiUnblocked();
			await shopperWCP.selectSavedPaymentMethod(
				`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
			);
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'should delete the card', async () => {
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.deleteSavedPaymentMethod( card.label );
			await expect( page ).toMatch( 'Payment method deleted' );
		} );
	} );

	describe( 'My Account', () => {
		let timeAdded;
		it( 'should add the card as a new payment method', async () => {
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.addNewPaymentMethod( 'basic', card );

			// Take note of the time when we added this card
			timeAdded = Date.now();

			// Verify that the card was added
			await expect( page ).not.toMatch(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			);
			await expect( page ).toMatch( 'Payment method successfully added' );
			await expect( page ).toMatch(
				`${ card.expires.month }/${ card.expires.year }`
			);
		} );

		it( 'should be able to delete the card', async () => {
			await shopperWCP.deleteSavedPaymentMethod( card.label );
			await expect( page ).toMatch( 'Payment method deleted.' );
		} );

		afterAll( async () => {
			// Make sure that at least 20s had already elapsed since the last card was added.
			// Otherwise, you will get the error message,
			// "You cannot add a new payment method so soon after the previous one."
			const timeTestFinished = Date.now();
			const elapsedWaitTime = timeTestFinished - timeAdded;
			const remainingWaitTime =
				MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS > elapsedWaitTime
					? MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS - elapsedWaitTime
					: 0;

			await new Promise( ( r ) => setTimeout( r, remainingWaitTime ) );
		} );
	} );

	const bnplProviders = [
		[ 'Affirm', 'li.payment_method_woocommerce_payments_affirm', 'button' ],
		// [
		// 	'Afterpay/Clearpay',
		// 	'li.payment_method_woocommerce_payments_afterpay_clearpay',
		// 	'a',
		// ],
	];

	describe.each( bnplProviders )(
		'Checkout with BNPL providers',
		( providerName, paymentMethodSelector, stripeButtonHTMLElement ) => {
			it( `should successfully place order with ${ providerName }`, async () => {
				await shopperWCP.changeAccountCurrencyTo( 'USD' );
				await setupProductCheckout(
					config.get( 'addresses.customer.billing' ),
					[ [ 'Beanie', 3 ] ]
				);
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
