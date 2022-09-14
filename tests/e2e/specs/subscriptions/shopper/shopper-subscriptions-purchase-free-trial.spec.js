/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper, withRestApi } = require( '@woocommerce/e2e-utils' );
import {
	RUN_SUBSCRIPTIONS_TESTS,
	describeif,
	merchantWCP,
} from '../../../utils';
import {
	fillCardDetails,
	setupCheckout,
	confirmCardAuthentication,
} from '../../../utils/payments';

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
const customerBilling = config.get(
	'addresses.subscriptions-customer.billing'
);
let orderId;

const testSelectors = {
	productSubscriptionDetails: '.subscription-details',
	cartSubscriptionFirstPaymentDate: '.first-payment-date',
	cartOrderTotal: '.order-total',
	checkoutSubscriptionDetails: '.subscription-details',
	checkoutSubscriptionFirstPaymentDate: '.first-payment-date',
	checkoutPlaceOrderButton: '#place_order',
	checkoutOrderId: '.woocommerce-order-overview__order.order > strong',
	wcOrderPaymentId: '.woocommerce-order-data__meta',
	subscriptionStatus: '.subscription-status',
	subscriptionProductName: '.order-item',
	subscriptionRecurringTotal: '.recurring_total',
	subscriptionTrialEnd: '.trial_end_date',
};

describeif( RUN_SUBSCRIPTIONS_TESTS )(
	'Subscriptions > Purchase subscription with free trial',
	() => {
		beforeAll( async () => {
			// Delete the user, if present
			await withRestApi.deleteCustomerByEmail( customerBilling.email );
		} );
		afterAll( async () => {
			await merchant.logout();
		} );

		it( 'should be able to purchase a subscription with free trial', async () => {
			// Open the subscription product, and verify that the
			// 14-day free trial is shown in the product description
			await page.goto( config.get( 'url' ) + `product/${ productSlug }`, {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatchElement(
				testSelectors.productSubscriptionDetails,
				{
					text: '/ month with a 14-day free trial',
				}
			);

			// Add it to the cart and verify that the cart page shows the free trial details
			await shopper.addToCart();
			await shopper.goToCart();
			await expect( page ).toMatchElement(
				testSelectors.productSubscriptionDetails,
				{
					text: '/ month with a 14-day free trial',
				}
			);

			// Also verify that the first renewal is 14 days from now
			await expect( page ).toMatchElement(
				testSelectors.cartSubscriptionFirstPaymentDate,
				{
					text: `First renewal: ${ renewalDateFormatted }`,
				}
			);

			// Verify that the order total is $0.00
			await expect( page ).toMatchElement( testSelectors.cartOrderTotal, {
				text: '$0.00',
			} );

			// Proceed to the checkout page and verify that the 14-day free trial is shown in the product line item,
			// and that the first renewal date is 14 days from now.
			await setupCheckout( customerBilling );
			await expect( page ).toMatchElement(
				testSelectors.checkoutSubscriptionDetails,
				{
					text: '/ month with a 14-day free trial',
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.cartSubscriptionFirstPaymentDate,
				{
					text: `First renewal: ${ renewalDateFormatted }`,
				}
			);

			// Pay using a 3DS card
			const card = config.get( 'cards.3dsOTP' );
			await fillCardDetails( page, card );
			await expect( page ).toClick(
				testSelectors.checkoutPlaceOrderButton
			);
			await confirmCardAuthentication( page, '3DS', true );
			await page.waitForNavigation( {
				waitUntil: 'networkidle0',
			} );
			await expect( page ).toMatch( 'Order received' );

			// Get the order ID so we can open it in the merchant view
			const orderIdField = await page.$( testSelectors.checkoutOrderId );
			orderId = await orderIdField.evaluate( ( el ) => el.innerText );

			await shopper.logout();
		} );

		it( 'should create an order with "Setup Intent"', async () => {
			await merchant.login();

			await merchant.goToOrder( orderId );
			await expect( page ).toMatchElement(
				testSelectors.wcOrderPaymentId,
				{
					text: /\(seti_.*\)/,
				}
			);

			await merchantWCP.openSubscriptions();

			// Verify we have an active subscription for the product
			await expect( page ).toMatchElement(
				testSelectors.subscriptionStatus,
				{
					text: 'Active',
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.subscriptionProductName,
				{
					text: productName,
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.subscriptionRecurringTotal,
				{
					text: '$9.99 / month',
				}
			);
			await expect( page ).toMatchElement(
				testSelectors.subscriptionTrialEnd,
				{
					text: renewalDateFormatted,
				}
			);
		} );
	}
);
