/**
 * External dependencies
 */
import config from 'config';

const { shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { shopperWCP } from '../../../utils/flows';

import { /*RUN_SUBSCRIPTIONS_TESTS,*/ describeif } from '../../../utils';
const { withRestApi } = require( '@woocommerce/e2e-utils' );
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
const card = config.get( 'cards.basic' );

const a = true;

describeif( a )( 'Setting Default Payment Method', () => {
	it( 'using a basic card', async () => {
		await withRestApi.deleteCustomerByEmail( customerBilling.email );

		await shopper.login();

		// Purchase multiple subscriptions.
		for ( let i = 0; 2 > i; i++ ) {
			await shopperWCP.addToCartBySlug(
				'subscription-signup-fee-product'
			);
			await setupCheckout( customerBilling );
			await fillCardDetails( page, card );
			await shopper.placeOrder();
		}

		await shopperWCP.goToPaymentMethods();
		await shopperWCP.addNewPaymentMethod( 'basic', card );

		await expect( page ).toMatchElement(
			'.woocommerce-MyAccount-content .woocommerce-info',
			{
				text:
					'Would you like to update your subscriptions to use this new payment method',
			}
		);

		await shopperWCP.logout();
	} );
} );
