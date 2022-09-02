/**
 * External dependencies
 */
import config from 'config';
const { shopper, withRestApi } = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	shopperWCP,
} from '../../../utils';
import { fillCardDetails, setupCheckout } from '../../../utils/payments';

const products = {
	'Subscription no signup fee product': 'subscription-no-signup-fee-product',
	'Subscription signup fee product': 'subscription-signup-fee-product',
};
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
const card = config.get( 'cards.basic' );

const testSelectors = {
	myAccountSubscriptionsId: 'td.subscription-id',
	myAccountSubscriptionsViewButton: 'td.subscription-actions a',
	myAccountSubscriptionOrderItems:
		'.shop_table order_details tbody tr.order_item',
	myAccountSubscriptionProductName: '.product-name',
	myAccountSubscriptionProductTotal: '.product-total',
	myAccountSubscriptionRecurringTotal: '.shop_table tfoot tr:last-child td',
	myAccountSubscriptionOrderTotal: 'td.order-total',
};

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase multiple subscriptions',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );
		afterAll( async () => {
			await shopper.logout();
		} );

		it( 'should be able to purchase multiple subscriptions', async () => {
			// As a Shopper, purchase the subscription products.
			const productSlugs = Object.values( products );
			for ( const productSlug of productSlugs ) {
				await shopperWCP.addToCartBySlug( productSlug );
			}

			await setupCheckout( customerBilling );
			await fillCardDetails( page, card );
			await shopper.placeOrder();

			// Navigate to 'My account -> Subscriptions'.
			await shopperWCP.goToSubscriptions();

			// Ensure the listing contains only one new entry
			const entriesCount = await page.$$eval(
				testSelectors.myAccountSubscriptionsId,
				( els ) => els.length
			);
			expect( entriesCount ).toEqual( 1 );

			// Click the 'View' button on the right side of the entry
			await expect( page ).toClick(
				testSelectors.myAccountSubscriptionsViewButton,
				{
					text: 'View',
				}
			);
			await page.waitForNavigation( { waitUntil: 'networkidle0' } );

			// Ensure 'Subscription totals' section lists the subscription products with the correct price.
			const subTotalsRows = await page.$$(
				testSelectors.myAccountSubscriptionOrderItems
			);
			for ( let i = 0; i < subTotalsRows.length; i++ ) {
				const row = subTotalsRows[ i ];

				await expect( row ).toMatchElement(
					testSelectors.myAccountSubscriptionProductName,
					{
						text: Object.keys( products )[ i ],
					}
				);
				await expect( row ).toMatchElement(
					testSelectors.myAccountSubscriptionProductTotal,
					{
						text: '$9.99 / month',
					}
				);
			}
			await expect( page ).toMatchElement(
				testSelectors.myAccountSubscriptionRecurringTotal,
				{
					text: '$19.98 / month',
				}
			);

			// Confirm related order total matches payment
			await expect( page ).toMatchElement(
				testSelectors.myAccountSubscriptionOrderTotal,
				{
					text: '$21.97 for 2 items',
				}
			);
		} );
	}
);
