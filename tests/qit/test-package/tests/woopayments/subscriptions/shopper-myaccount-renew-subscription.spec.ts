/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';

const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Renew a subscription in my account',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		// The renewal needs the subscription bought by the first test.
		test.describe.configure( { mode: 'serial' } );

		let subscriptionId: string;

		test( 'should be able to purchase a subscription', async ( {
			customerPage,
		} ) => {
			await shopper.emptyCart( customerPage );
			await shopper.placeOrderWithOptions( customerPage, {
				product: config.products.subscription_signup_fee,
				billingAddress: customerBillingConfig,
			} );

			subscriptionId = await customerPage
				.getByLabel( 'View subscription number' )
				.innerText();
		} );

		test( 'should be able to renew a subscription in my account', async ( {
			customerPage,
		} ) => {
			await navigation.goToSubscriptions( customerPage );

			if ( ! subscriptionId ) {
				throw new Error( 'Subscription ID is not set' );
			}

			const numericSubscriptionId = subscriptionId.substring( 1 );

			await customerPage
				.getByLabel(
					`View subscription number ${ numericSubscriptionId }`
				)
				.click();

			await customerPage.getByText( 'Renew now' ).click();
			await expect(
				customerPage.getByText( 'Complete checkout to renew now.' )
			).toBeVisible();
			await shopper.focusPlaceOrderButton( customerPage );
			await shopper.placeOrder( customerPage );
			await customerPage.waitForURL( /\/order-received\// );
			await expect(
				customerPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();
		} );
	}
);
