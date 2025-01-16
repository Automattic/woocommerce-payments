/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { shouldRunSubscriptionsTests } from '../../utils/constants';
import { describeif, getMerchant, getShopper } from '../../utils/helpers';
import RestAPI from '../../utils/rest-api';
import { config } from '../../config/default';
import { goToSubscriptions, goToOrder } from '../../utils/merchant-navigation';
import {
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../utils/shopper';
import { goToProductPageBySlug } from '../../utils/shopper-navigation';

const productName = 'Subscription no signup fee product';
const productSlug = 'subscription-no-signup-fee-product';

const customerBillingConfig = config.addresses.customer.billing;
let orderId;

describeif( shouldRunSubscriptionsTests )(
	'Shopper Subscriptions Purchase No Signup Fee',
	() => {
		test.beforeAll( async ( {}, { project } ) => {
			const restApi = new RestAPI( project.use.baseURL );
			await restApi.deleteCustomerByEmailAddress(
				customerBillingConfig.email
			);
		} );
		test( 'It should be able to purchase a subscription without a signup fee', async ( {
			browser,
		} ) => {
			const { shopperPage } = await getShopper( browser );
			await goToProductPageBySlug( shopperPage, productSlug );
			await shopperPage
				.getByRole( 'button', { name: 'Sign up now' } )
				.click();
			await shopperPage.waitForLoadState( 'networkidle' );
			await expect(
				shopperPage.getByText( /has been added to your cart\./ )
			).toBeVisible();
			await setupCheckout( shopperPage, customerBillingConfig );
			const card = config.cards.basic;
			await fillCardDetails( shopperPage, card );
			await placeOrder( shopperPage );
			expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			const orderIdField = shopperPage.locator(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = await orderIdField.textContent();
		} );
		test( 'It should have a charge for subscription cost without fee & an active subscription', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );
			await goToOrder( merchantPage, orderId );

			// Verify we have an active subscription
			const relatedSubscriptionId = (
				await merchantPage
					.getByRole( 'cell', { name: 'Edit order number' } )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );

			const transactionPageLink = await merchantPage
				.getByText( 'Payment via Credit card /' )
				.getByRole( 'link', { name: /pi_.+/ } )
				.getAttribute( 'href' );

			await merchantPage.goto( transactionPageLink, {
				waitUntil: 'load',
			} );

			await expect(
				merchantPage.getByText(
					'A payment of $9.99 was successfully charged.'
				)
			).toBeVisible();

			await goToSubscriptions( merchantPage );

			const subscriptionsRow = merchantPage.locator(
				'#order-' + relatedSubscriptionId
			);

			await expect(
				subscriptionsRow.locator( '.subscription-status' )
			).toHaveText( 'Active' );

			await expect(
				subscriptionsRow.locator( '.order_items' )
			).toHaveText( productName );

			await expect(
				subscriptionsRow.locator( '.recurring_total' )
			).toHaveText( /\$9\.99 \/ month/i );
		} );
	}
);
