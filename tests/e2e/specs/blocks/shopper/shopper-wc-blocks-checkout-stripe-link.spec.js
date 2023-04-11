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
	randomizeEmail,
	getInputTextValue,
} from '../../../utils';

import { fillUpeCardDetailsWCP } from '../../../utils/payments';

const productName = config.get( 'products.simple.name' );
const linkPaymentsCheckbox = '#inspector-checkbox-control-10';

describeif( RUN_WC_BLOCKS_TESTS )(
	'WooCommerce Blocks > Stripe Link usage',
	() => {
		const billingAddress = {
			...config.get( 'addresses.customer.billing' ),
			email: randomizeEmail(
				config.get( 'addresses.customer.billing.email' )
			),
			phone: '14587777777',
		};

		beforeAll( async () => {
			await merchant.login();
			await merchantWCP.activateUpe();
			await merchantWCP.enablePaymentMethod( linkPaymentsCheckbox );
			try {
				await checkPageExists( 'checkout-wcb' );
			} catch ( error ) {
				await merchantWCP.addNewPageCheckoutWCB();
			}
			await merchant.logout();
		} );

		afterAll( async () => {
			await merchant.login();
			await merchantWCP.disablePaymentMethod(
				linkPaymentsCheckbox,
				false
			);
			await merchantWCP.deactivateUpe();
			await merchant.logout();
		} );

		it( 'should save account details to Link', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();
			await shopperWCP.fillBillingDetailsWCB( billingAddress );

			const card = config.get( 'cards.basic' );
			await fillUpeCardDetailsWCP( page, card );
			await shopperWCP.fillStripeLinkDetails( billingAddress, true );

			await page.waitForSelector(
				'.wc-block-components-main button:not(:disabled)'
			);
			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );

		it( 'should use the saved Link account to fill in the details', async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage( productName );
			await shopperWCP.openCheckoutWCB();

			await expect( page ).toFill(
				'#billing-phone',
				billingAddress.phone
			);
			await expect( page ).toFill( '#email', billingAddress.email );
			await shopperWCP.autofillExistingStripeLinkAccount();

			expect( billingAddress.firstname ).toContain(
				await getInputTextValue( '#billing-first_name' )
			);
			expect( await getInputTextValue( '#billing-last_name' ) ).toContain(
				billingAddress.lastname
			);
			expect( await getInputTextValue( '#billing-city' ) ).toEqual(
				billingAddress.city
			);
			expect(
				await getInputTextValue( '#components-form-token-input-1' )
			).toBeTruthy();
			expect( await getInputTextValue( '#billing-postcode' ) ).toEqual(
				billingAddress.postcode
			);
			expect(
				await getInputTextValue( '#components-form-token-input-0' )
			).toEqual( billingAddress.country );

			await expect( page ).toClick( 'button', { text: 'Place Order' } );
			await page.waitForSelector( 'div.woocommerce-order' );
			await expect( page ).toMatch( 'p', {
				text: 'Thank you. Your order has been received.',
			} );
		} );
	}
);
