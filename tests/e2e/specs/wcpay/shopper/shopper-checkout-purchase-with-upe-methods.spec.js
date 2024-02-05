/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import {
	setupProductCheckout,
	selectOnCheckout,
	completeRedirectedPayment,
} from '../../../utils/payments';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const UPE_METHOD_CHECKBOXES = [
	"//label[contains(text(), 'Bancontact')]/preceding-sibling::span/input[@type='checkbox']",
	"//label[contains(text(), 'giropay')]/preceding-sibling::span/input[@type='checkbox']",
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
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
	} );

	afterAll( async () => {
		await shopperWCP.logout();
		await merchant.login();
		await merchantWCP.disablePaymentMethod( UPE_METHOD_CHECKBOXES );
		await merchant.logout();
	} );

	describe( 'Enabled UPE with deferred intent creation', () => {
		it( 'should successfully place order with Giropay', async () => {
			await setupProductCheckout(
				config.get( 'addresses.upe-customer.billing.de' )
			);
			page.waitFor( 1000 );
			await selectOnCheckout( 'giropay', page );
			await shopper.placeOrder();
			await completeRedirectedPayment( page, 'success' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'should successfully place order with Bancontact', async () => {
			await setupProductCheckout(
				config.get( 'addresses.upe-customer.billing.be' )
			);
			page.waitFor( 1000 );
			await selectOnCheckout( 'bancontact', page );
			await shopper.placeOrder();
			await completeRedirectedPayment( page, 'success' );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );
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
