/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { shouldRunSubscriptionsTests } from '../../utils/constants';
import { describeif, getShopper } from '../../utils/helpers';
import RestAPI from '../../utils/rest-api';
import { config } from '../../config/default';
import {
	emptyCart,
	fillCardDetails,
	placeOrder,
	setupProductCheckout,
} from '../../utils/shopper';
import {
	goToShop,
	goToShopWithCurrency,
	goToSubscriptions,
} from '../../utils/shopper-navigation';
import { getByText } from '@testing-library/react';

const products = {
	'Subscription no signup fee product': 'subscription-no-signup-fee-product',
	'Subscription signup fee product': 'subscription-signup-fee-product',
};
const configBillingAddress = config.addresses.customer.billing;

describeif( shouldRunSubscriptionsTests )(
	'Subscriptions > Purchase multiple subscriptions',
	() => {
		test.beforeAll( async ( {}, { project } ) => {
			const restApi = new RestAPI( project.use.baseURL );
			await restApi.deleteCustomerByEmailAddress(
				configBillingAddress.email
			);
		} );

		test( ' should be able to purchase multiple subscriptions', async ( {
			browser,
		} ) => {
			// As a Shopper, purchase the subscription products.
			const { shopperPage } = await getShopper( browser );
			await emptyCart( shopperPage );
			await goToShopWithCurrency( shopperPage, 'USD' );
			await goToShop( shopperPage, 2 );
			await setupProductCheckout(
				shopperPage,
				Object.keys( products ).map( ( productName: string ) => [
					productName,
					1,
				] ),
				configBillingAddress
			);
			await fillCardDetails( shopperPage, config.cards.basic );
			await placeOrder( shopperPage );
			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			const subscriptionId = (
				await shopperPage
					.getByLabel( 'View subscription number' )
					.innerText()
			 )
				.trim()
				.replace( '#', '' );

			await goToSubscriptions( shopperPage );

			const latestSubscriptionRow = shopperPage.getByRole( 'row', {
				name: `subscription number ${ subscriptionId }`,
			} );

			await expect( latestSubscriptionRow ).toBeVisible();
			await latestSubscriptionRow
				.getByRole( 'link', {
					name: 'View',
				} )
				.nth( 0 )
				.click();

			await shopperPage.waitForLoadState( 'networkidle' );

			// Ensure 'Subscription totals' section lists the subscription products with the correct price.
			const subTotalsRows = shopperPage.locator(
				'.order_details tr.order_item'
			);
			for ( let i = 0; i < ( await subTotalsRows.count() ); i++ ) {
				const row = subTotalsRows.nth( i );
				await expect( row.getByRole( 'cell' ).nth( 1 ) ).toContainText(
					Object.keys( products )[ i ]
				);

				await expect( row.getByRole( 'cell' ).nth( 2 ) ).toContainText(
					'$9.99 / month'
				);
			}

			await expect(
				shopperPage
					.getByRole( 'row', { name: 'total:' } )
					.getByRole( 'cell' )
					.nth( 1 )
			).toContainText( '$19.98 USD / month' );

			// Confirm related order total matches payment
			await expect(
				shopperPage.getByText( '$21.97 USD for 2 items' )
			).toBeVisible();
		} );
	}
);
