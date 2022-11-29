/* eslint-disable jest/no-test-prefixes */

/**
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

describeif( RUN_WC_BLOCKS_TESTS )( 'WooCommerce Blocks > Saved cards', () => {
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

	it( 'should be able to save basic card on Blocks checkout', async () => {
		await shopper.goToShop();
		await shopper.addToCartFromShopPage( productName );
		await shopperWCP.openCheckoutWCB();
		await shopperWCP.fillBillingDetailsWCB( billingDetails );

		// Fill CC details and save the card while purchasing the product
		const savePaymentMethodButton = '#checkbox-control-0';
		await fillCardDetailsWCB( page, card );
		await expect( page ).toClick( savePaymentMethodButton );

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
