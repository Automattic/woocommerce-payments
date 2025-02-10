/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import RestAPI from '../../../utils/rest-api';
import { config } from '../../../config/default';
import { describeif, getMerchant, getShopper } from '../../../utils/helpers';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptionPage } from '../../../utils/merchant-navigation';
import { shouldRunSubscriptionsTests } from '../../../utils/constants';

const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;
let subscriptionId = null;

describeif( shouldRunSubscriptionsTests )(
	'Subscriptions > Renew a subscription as a merchant',
	() => {
		test.beforeAll( async ( { browser }, { project } ) => {
			const restApi = new RestAPI( project.use.baseURL );
			restApi.deleteCustomerByEmailAddress( customerBillingConfig.email );

			const { shopperPage } = await getShopper( browser );
			await emptyCart( shopperPage );
			await setupProductCheckout(
				shopperPage,
				[ [ config.products.subscription_signup_fee, 1 ] ],
				customerBillingConfig
			);
			await fillCardDetails( shopperPage, config.cards.basic );
			await focusPlaceOrderButton( shopperPage );
			await placeOrder( shopperPage );
			await shopperPage.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			// Get the subscription ID
			subscriptionId = (
				await shopperPage
					.getByRole( 'link', { name: 'View subscription number' } )
					.textContent()
			 )
				.trim()
				.replace( '#', '' );
		} );

		test( 'should be able to renew a subscription in my account', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );
			await goToSubscriptionPage( merchantPage, subscriptionId );
			await expect(
				merchantPage.getByRole( 'heading', {
					name: 'Edit Subscription',
				} )
			).toBeVisible();
			const orderActions = merchantPage.locator(
				'select[name="wc_order_action"]'
			);
			await orderActions.selectOption( { label: 'Process renewal' } );

			// Prepare to accept the dialog before clicking the submit button.
			// Since the page will change, we don't need to remove the listener.
			merchantPage.on( 'dialog', async ( dialog ) => {
				await dialog.accept();
			} );
			await merchantPage
				.locator( '#actions' )
				.getByRole( 'button', { name: /Apply.+/i } )
				.click();
			await merchantPage.waitForLoadState( 'networkidle' );

			// Check if a new order is present in related orders
			await expect(
				merchantPage.getByRole( 'cell', { name: 'Renewal Order' } )
			).toBeVisible();
		} );
	}
);
