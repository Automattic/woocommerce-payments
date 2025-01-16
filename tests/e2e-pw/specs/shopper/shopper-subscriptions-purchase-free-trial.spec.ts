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
import {
	confirmCardAuthentication,
	emptyCart,
	fillCardDetails,
	setupCheckout,
} from '../../utils/shopper';
import {
	goToCart,
	goToProductPageBySlug,
} from '../../utils/shopper-navigation';
import { goToOrder, goToSubscriptions } from '../../utils/merchant-navigation';

const nowLocal = new Date();
const nowUTC = new Date(
	nowLocal.getUTCFullYear(),
	nowLocal.getUTCMonth(),
	nowLocal.getUTCDate()
);
const formatter = new Intl.DateTimeFormat( 'en-US', {
	dateStyle: 'long',
} );
const renewalDate = nowUTC.setDate( nowUTC.getDate() + 14 );
const renewalDateFormatted = formatter.format( renewalDate );
const productName = 'Subscription free trial product';
const productSlug = 'subscription-free-trial-product';
const customerBilling = config.addresses.customer.billing;
const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;
let orderId, subscriptionId;

const testSelectors = {
	productSubscriptionDetails: '.summary span.subscription-details',
	cartSubscriptionDetails: '.product-price span.subscription-details',
	cartSubscriptionFirstPaymentDate: '.first-payment-date',
	cartOrderTotal: 'tr.order-total:not(.recurring-total) td',
	checkoutSubscriptionDetails: 'span.subscription-details',
	checkoutSubscriptionFirstPaymentDate: '.first-payment-date',
	checkoutPlaceOrderButton: '#place_order',
	checkoutOrderId: '.woocommerce-order-overview__order.order > strong',
	checkoutSubscriptionId: 'td.subscription-id > a',
	wcOrderPaymentId: '.woocommerce-order-data__meta',
	subscriptionStatus: '.subscription-status',
	subscriptionProductName: '.order-item',
	subscriptionRecurringTotal: '.recurring_total',
	subscriptionTrialEnd: 'time.trial_end_date',
};

describeif( shouldRunSubscriptionsTests )(
	'Shopper: Subscriptions - Purchase Free Trial',
	() => {
		test.beforeAll( async ( {}, { project } ) => {
			const restApi = new RestAPI( project.use.baseURL );
			await restApi.deleteCustomerByEmailAddress(
				customerBillingConfig.email
			);
		} );

		test( 'Merchant should be able to purchase a free trial', async ( {
			browser,
		} ) => {
			const { shopperPage } = await getShopper( browser );

			// Just to be sure, empty the cart
			await emptyCart( shopperPage );

			// Open the subscription product, and verify that the
			// 14-day free trial is shown in the product description
			await goToProductPageBySlug( shopperPage, productSlug );
			await expect(
				shopperPage.locator( testSelectors.productSubscriptionDetails )
			).toHaveText( /\s*\/ month with a 14-day free trial\s*/ );

			// Add it to the cart and verify that the cart page shows the free trial details
			await shopperPage.locator( '.single_add_to_cart_button' ).click();
			await goToCart( shopperPage );
			await expect(
				shopperPage.locator( testSelectors.cartSubscriptionDetails )
			).toHaveText( /\s*\/ month with a 14-day free trial\s*/ );

			// Also verify that the first renewal is 14 days from now
			await expect(
				shopperPage.locator(
					testSelectors.cartSubscriptionFirstPaymentDate
				)
			).toHaveText( `First renewal: ${ renewalDateFormatted }` );

			// Verify that the order total is $0.00
			await expect(
				shopperPage.locator( testSelectors.cartOrderTotal )
			).toHaveText( `$0.00` );

			// Proceed to the checkout page and verify that the 14-day free trial is shown in the product line item,
			// and that the first renewal date is 14 days from now.
			await setupCheckout( shopperPage, customerBilling );
			await expect(
				shopperPage.locator( testSelectors.checkoutSubscriptionDetails )
			).toHaveText( '/ month with a 14-day free trial' );
			await expect(
				shopperPage.locator(
					testSelectors.cartSubscriptionFirstPaymentDate
				)
			).toHaveText( `First renewal: ${ renewalDateFormatted }` );

			// Pay using a 3DS card
			const card = config.cards[ '3dsOTP' ];
			await fillCardDetails( shopperPage, card );
			await shopperPage.click( testSelectors.checkoutPlaceOrderButton );
			await shopperPage.frames()[ 0 ].waitForLoadState( 'load' );
			await confirmCardAuthentication( shopperPage, true );
			await shopperPage.waitForLoadState( 'networkidle' );
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await shopperPage.$(
				testSelectors.checkoutOrderId
			);
			orderId = await orderIdField.evaluate( ( el ) => el.textContent );
			const subscriptionIdField = await shopperPage.$(
				testSelectors.checkoutSubscriptionId
			);
			subscriptionId = await subscriptionIdField.evaluate( ( el ) =>
				el.textContent.trim().replace( '#', '' )
			);
		} );

		test( 'Merchant should be able to create an order with "Setup Intent"', async ( {
			browser,
		} ) => {
			const { merchantPage } = await getMerchant( browser );
			await goToOrder( merchantPage, orderId );
			await expect(
				merchantPage.locator( testSelectors.wcOrderPaymentId )
			).toHaveText( /\(seti_.*\)/ );

			await goToSubscriptions( merchantPage );
			const subscriptionRow = await merchantPage.locator(
				'#order-' + subscriptionId
			);
			await expect(
				subscriptionRow.locator( testSelectors.subscriptionStatus )
			).toHaveText( 'Active' );
			await expect(
				subscriptionRow.locator( testSelectors.subscriptionProductName )
			).toHaveText( productName );
			await expect(
				subscriptionRow.locator(
					testSelectors.subscriptionRecurringTotal
				)
			).toHaveText( /\$9\.99/ );
			await expect(
				subscriptionRow.locator( testSelectors.subscriptionTrialEnd )
			).toHaveText( renewalDateFormatted );
		} );
	}
);
