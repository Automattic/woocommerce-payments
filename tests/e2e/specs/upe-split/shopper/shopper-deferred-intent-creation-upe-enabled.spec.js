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
	selectGiropayOnCheckout,
	completeGiropayPayment,
} from '../../../utils/payments';
import { uiUnblocked } from '@woocommerce/e2e-utils/build/page-utils';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const UPE_METHOD_CHECKBOXES = [
	'#inspector-checkbox-control-7', // giropay
];
const card = config.get( 'cards.basic' );
const card2 = config.get( 'cards.basic2' );
const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 20000;

describe( 'Enabled UPE with deferred intent creation', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.enablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
		await shopper.login();
	} );

	afterAll( async () => {
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
	} );

	describe( 'Enabled UPE with deferred intent creation', () => {
		it( 'should successfully place order with Giropay', async () => {
			await shopperWCP.goToShopWithCurrency( 'EUR' );
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await selectGiropayOnCheckout( page );
			await shopper.placeOrder();
			await completeGiropayPayment( page, 'success' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );

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

		it( 'should not allow guest user to save the card', async () => {
			await shopperWCP.logout();
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);

			await expect( page ).not.toMatchElement(
				'input#wc-woocommerce_payments-new-payment-method'
			);
			await shopper.login();
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
			await waitTwentySecondsSinceLastCardAdded();
		} );

		it( 'should be able to set payment method as default', async () => {
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.addNewPaymentMethod( 'basic2', card2 );
			// Take note of the time when we added this card
			timeAdded = Date.now();

			// Verify that the card was added
			await expect( page ).not.toMatch(
				'You cannot add a new payment method so soon after the previous one. Please wait for 20 seconds.'
			);
			await expect( page ).toMatch( 'Payment method successfully added' );
			await expect( page ).toMatch(
				`${ card2.expires.month }/${ card2.expires.year }`
			);
			await shopperWCP.setDefaultPaymentMethod( card2.label );
			// Verify that the card was set as default
			await expect( page ).toMatch(
				'This payment method was successfully set as your default.'
			);
		} );

		it( 'should be able to delete cards', async () => {
			await shopperWCP.deleteSavedPaymentMethod( card.label );
			await expect( page ).toMatch( 'Payment method deleted.' );

			await shopperWCP.deleteSavedPaymentMethod( card2.label );
			await expect( page ).toMatch( 'Payment method deleted.' );
		} );

		afterAll( async () => {
			await waitTwentySecondsSinceLastCardAdded();
		} );

		async function waitTwentySecondsSinceLastCardAdded() {
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
		}
	} );
} );
