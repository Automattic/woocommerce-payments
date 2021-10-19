/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );

import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
	shopperWCP,
} from '../../utils';

import { fillCardDetails, setupCheckout } from '../../utils/payments';

const productNames = [
	`Multi sub 1 ${ Date.now() }`,
	`Multi sub 2 ${ Date.now() }`,
];
const customerBilling = config.get( 'addresses.customer.billing' );
const card = config.get( 'cards.basic' );
const productIds = [];

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase multiple subscriptions',
	() => {
		// Setup 2 subscription products.
		// Remember their product id's.
		beforeAll( async () => {
			await merchant.login();

			for ( const productName of productNames ) {
				const productId = await merchantWCP.createSubscriptionProduct(
					productName,
					'month'
				);
				productIds.push( productId );
			}

			await merchant.logout();
		} );

		afterAll( async () => {
			// Delete the user created with the subscription
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );

		it( 'should be able to purchase multiple subscriptions', async () => {
			// As a Shopper, purchase the subscription products.
			for ( const productId of productIds ) {
				await shopper.goToProduct( productId );
				await shopper.addToCart();
			}

			await setupCheckout( customerBilling );
			await fillCardDetails( page, card );
			await shopper.placeOrder();

			// Navigate to 'My account -> Subscriptions'.
			await shopperWCP.goToSubscriptions();

			// Ensure the listing contains only one new entry
			const entriesCount = await page.$$eval(
				'td.subscription-id',
				( els ) => els.length
			);
			expect( entriesCount ).toEqual( 1 );

			// Click the 'View' button on the right side of the entry
			await expect( page ).toClick( 'td.subscription-actions a', {
				text: 'View',
			} );
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Ensure 'Subscription totals' section lists the subscription products with the correct price.
			const subTotalsRows = await page.$$( 'tr.order_item' );
			for ( let i = 0; i < subTotalsRows.length; i++ ) {
				const row = subTotalsRows[ i ];

				await expect( row ).toMatchElement( '.product-name', {
					text: productNames[ i ],
				} );
				await expect( row ).toMatchElement( '.product-total', {
					text: '$9.99 / month',
				} );
			}
			await expect( page ).toMatchElement( 'td.order-total', {
				text: '$19.98 for 2 items',
			} );
		} );
	}
);
