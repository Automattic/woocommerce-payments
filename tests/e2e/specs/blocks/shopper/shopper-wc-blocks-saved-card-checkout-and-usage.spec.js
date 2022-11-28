/* eslint-disable jest/no-test-prefixes */

/**
 * Important note:
 * This test won't be successful in -dev (non-headless) mode, because different selectors are present in page editor.
 * Speaking of addNewPageCheckoutWCB() method when creating a new Checkout page with WCB block inside (37th line).
 * Might be Chromium browser's behavior, but good to mention that the correct selectors are present in headless mode,
 * so testing headless would be what the user really see in Chrome, Firefox, Opera...
 *
 * External dependencies
 */
import config from 'config';

const { shopper, merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	shopperWCP,
	merchantWCP,
	describeif,
	RUN_WC_BLOCKS_TESTS,
	checkPageExists,
} from '../../../utils';

const billingDetails = config.get( 'addresses.customer.billing' );
const productName = config.get( 'products.simple.name' );
const card = config.get( 'cards.basic' );

import { fillCardDetailsWCB } from '../../../utils/payments';

describeif( RUN_WC_BLOCKS_TESTS )(
	'WooCommerce Blocks > Successful purchase',
	() => {
		beforeAll( async () => {
			try {
				await checkPageExists( 'checkout-wcb' );
			} catch ( error ) {
				await merchant.login();
				await merchantWCP.addNewPageCheckoutWCB();
				await merchant.logout();
			}
			await shopper.login();
		} );

		afterAll( async () => {
			await shopper.logout();
		} );

		it( 'should be able to save basic card during the checkout', async () => {
			// non-UPE
			// purchase with blocks and save the card
			// go to my account -> payment methods and confirm that the card is there
			// click add payment method and confirm the card is there on the list as well
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			// Fill CC details and save the card while purchasing the product
			await fillCardDetailsWCB( page, card );
			await expect( page ).toClick( '#checkbox-control-0' );

			await page.waitForSelector(
				'.wc-block-components-main button:not(:disabled)'
			);
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );

		it( 'should process a payment with the saved card from Blocks checkout page', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingDetails );

			await shopperWCP.selectSavedPaymentMethod(
				`${ card.label } (expires ${ card.expires.month }/${ card.expires.year })`
			);
			await shopper.placeOrder();
			await expect( page ).toMatch( 'Order received' );
		} );

		it( 'adding new payment method should not be the default option because the saved card is', async () => {
			await shopperWCP.goToPaymentMethods();
			await expect( page ).toClick( 'a', {
				text: 'Add payment method',
			} );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );

			const newPaymentMethodRadioButton = await page.$(
				'#wc-woocommerce_payments-payment-token-new'
			);
			const isNewPaymentMethodRadioButtonCheckedByDefault = await (
				await newPaymentMethodRadioButton.getProperty( 'checked' )
			 ).jsonValue();
			expect( isNewPaymentMethodRadioButtonCheckedByDefault ).toEqual(
				false
			);
		} );

		it( 'should be able to delete the card', async () => {
			await shopperWCP.goToPaymentMethods();
			await shopperWCP.deleteSavedPaymentMethod( card.label );
			await expect( page ).toMatch( 'Payment method deleted' );
		} );
	}
);
