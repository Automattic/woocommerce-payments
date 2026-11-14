/**
 * Internal dependencies
 */
import { test, expect, recreateCustomer } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import {
	goToOrder,
	goToSubscriptions,
	goToPaymentDetails,
	activateMulticurrency,
	deactivateMulticurrency,
	isMulticurrencyEnabled,
} from '../../../utils/merchant';

const productName = 'Subscription no signup fee product';
const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Shopper purchase subscription without signup fee',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let orderId: string;
		let subscriptionId: string;
		let wasMultiCurrencyEnabled: boolean;

		test.beforeAll( async () => {
			await recreateCustomer();
		} );

		test.beforeAll( async ( { adminPage, customerPage } ) => {
			// Check and disable multi-currency if enabled
			wasMultiCurrencyEnabled = await isMulticurrencyEnabled( adminPage );
			if ( wasMultiCurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}

			// Purchase the subscription
			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[ [ config.products.subscription_no_signup_fee, 1 ] ],
				customerBillingConfig
			);
			await fillCardDetails( customerPage, config.cards.basic );
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );
			await customerPage.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			// Get the order ID
			const orderIdField = customerPage.locator(
				'.woocommerce-order-overview__order.order > strong'
			);
			orderId = ( await orderIdField.textContent() ) ?? '';

			// Get the subscription ID
			const subscriptionLink = customerPage.getByRole( 'link', {
				name: 'View subscription number',
			} );
			const linkText = await subscriptionLink.textContent();
			subscriptionId = linkText?.trim().replace( '#', '' ) ?? '';
		} );

		test.afterAll( async ( { adminPage } ) => {
			// Enable multicurrency if it was enabled before
			if ( wasMultiCurrencyEnabled ) {
				await activateMulticurrency( adminPage );
			}
		} );

		test( 'should have a charge for subscription cost without fee & an active subscription', async ( {
			adminPage,
		} ) => {
			await goToOrder( adminPage, orderId );

			// Get the payment intent ID from the order
			const paymentIntentId = await adminPage
				.locator( '#order_data' )
				.getByRole( 'link', {
					name: /pi_/,
				} )
				.innerText();

			await goToPaymentDetails( adminPage, paymentIntentId );

			await expect(
				adminPage.getByText(
					'A payment of $9.99 was successfully charged.'
				)
			).toBeVisible();

			await goToSubscriptions( adminPage );

			let subscriptionsRow = adminPage.locator(
				'#order-' + subscriptionId
			);

			// Fallback for WC 7.7.0.
			if ( ( await subscriptionsRow.count() ) === 0 ) {
				subscriptionsRow = adminPage.locator(
					'#post-' + subscriptionId
				);
			}

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
