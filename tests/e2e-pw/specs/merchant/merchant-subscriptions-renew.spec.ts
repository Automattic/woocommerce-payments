/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import RestAPI from '../../utils/rest-api';
import { config } from '../../config/default';
import {
	getMerchant,
	getShopper,
	prepareAcceptingDialogs,
	removeDialogListener,
} from '../../utils/helpers';
import {
	emptyCart,
	ensureSavedCardNotSelected,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../utils/shopper';
import { goToShop } from '../../utils/shopper-navigation';
import { goToSubscriptionPage } from '../../utils/merchant-navigation';
import { after } from 'lodash';

const productName = 'Subscription signup fee product';
const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;
let subscriptionId = null;

test.describe( 'Admin order analytics', () => {
	test.beforeAll( async ( { browser }, { project } ) => {
		const restApi = new RestAPI( project.use.baseURL );
		restApi.deleteCustomerByEmailAddress( customerBillingConfig.email );

		const { shopperPage } = await getShopper( browser );
		await emptyCart( shopperPage );
		await goToShop( shopperPage, 2 );
		await setupProductCheckout(
			shopperPage,
			[ [ productName, 1 ] ],
			customerBillingConfig
		);
		await ensureSavedCardNotSelected( shopperPage );
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
		const subscriptionIdField = await shopperPage.locator(
			'.woocommerce-orders-table__cell-subscription-id > a'
		);
		subscriptionId = await subscriptionIdField.evaluate( ( el ) =>
			el.textContent.trim().replace( '#', '' )
		);
		// Give some time for the subscription to be created
		await shopperPage.waitForLoadState( 'networkidle' );
	} );

	test( 'should be able to renew a subscription in my account', async ( {
		browser,
	} ) => {
		const { merchantPage } = await getMerchant( browser );
		await goToSubscriptionPage( merchantPage, subscriptionId );
		await expect(
			merchantPage.getByRole( 'heading', {
				name: 'Edit Subscription',
				level: 1,
			} )
		).toBeVisible();
		const orderActions = await merchantPage.locator(
			'select[name="wc_order_action"]'
		);
		await orderActions.selectOption( { label: 'Process renewal' } );

		// Prepare to accept the dialog before clicking the submit button.
		// Since the page will change, we don't need to remove the listener.
		prepareAcceptingDialogs( merchantPage );
		await merchantPage.click( 'button:has-text("Apply")' );
		await merchantPage.waitForLoadState( 'networkidle' );

		// Check if a new order is present in related orders
		await expect(
			merchantPage.locator(
				'div.woocommerce_subscriptions_related_orders > table > tbody > tr > td',
				{ hasText: 'Renewal Order' }
			)
		).toBeVisible();
	} );
} );
