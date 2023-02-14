/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { merchantWCP, shopperWCP } from '../../../utils/flows';
import { checkPageExists } from '../../../utils';
import {
	fillCardDetails,
	fillCardDetailsWCB,
	setupProductCheckout,
} from '../../../utils/payments';
const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

const billingDetails = config.get( 'addresses.customer.billing' );
const productName = config.get( 'products.simple.name' );
const MIN_WAIT_TIME_BETWEEN_PAYMENT_METHODS = 20000;

const sepaPaymentMethod = '#inspector-checkbox-control-8';
const card = config.get( 'cards.basic' );

describe.skip( 'Enabled Split UPE', () => {
	beforeAll( async () => {
		await merchant.login();
		await merchantWCP.activateSplitUpe();
		// enable SEPA
		await merchantWCP.enablePaymentMethod( sepaPaymentMethod );
		try {
			await checkPageExists( 'checkout-wcb' );
		} catch ( error ) {
			await merchantWCP.addNewPageCheckoutWCB();
		}
		await merchant.logout();
		await shopper.login();
		await shopperWCP.changeAccountCurrencyTo( 'EUR' );
	} );

	afterAll( async () => {
		await shopperWCP.changeAccountCurrencyTo( 'USD' );
		await shopperWCP.logout();
		await merchant.login();
		//disable SEPA
		await merchantWCP.disablePaymentMethod( sepaPaymentMethod );
		await merchantWCP.deactivateSplitUpe();
		await merchant.logout();
	} );

	describe( 'Blocks checkout', () => {
		it( 'should checkout and save the card', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			// Fill CC details and save the card while purchasing the product
			const savePaymentMethodCheckbox = '#checkbox-control-0';
			await fillCardDetailsWCB( page, card );
			await expect( page ).toClick( savePaymentMethodCheckbox );

			await page.waitForSelector(
				'.wc-block-components-main button:not(:disabled)'
			);
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );

			await shopperWCP.goToPaymentMethods();
			await expect( page ).toMatch( card.label );
			await expect( page ).toMatch(
				`${ card.expires.month }/${ card.expires.year }`
			);
		} );

		it( 'should process a payment with the saved card from Blocks checkout', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			await shopperWCP.selectSavedPaymentMethod(
				`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
			);
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );

		it( 'saved payment method is checked by default when adding a new payment method', async () => {
			await shopperWCP.goToPaymentMethods();
			await expect( page ).toClick( 'a', {
				text: 'Add payment method',
			} );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			const isSavedPaymentTokenChecked = await page.$eval(
				'li.woocommerce-SavedPaymentMethods-token > input',
				( input ) => input.checked
			);
			expect( isSavedPaymentTokenChecked ).toEqual( true );
		} );

		it( 'should delete the card', async () => {
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.deleteSavedPaymentMethod( card.label );
			await expect( page ).toMatch( 'Payment method deleted' );
		} );
	} );

	describe( 'Shortcode checkout', () => {
		it( 'should save the card', async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
			await shopperWCP.selectNewPaymentMethod();
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
} );
