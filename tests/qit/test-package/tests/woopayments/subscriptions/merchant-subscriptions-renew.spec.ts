/**
 * External dependencies
 */
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { config } from '../../../config/default';
import {
	emptyCart,
	fillCardDetails,
	focusPlaceOrderButton,
	placeOrder,
	setupProductCheckout,
} from '../../../utils/shopper';
import { goToSubscriptionPage } from '../../../utils/merchant';

const customerBillingConfig =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Renew a subscription as a merchant',
	{ tag: [ '@merchant', '@subscriptions', '@critical' ] },
	() => {
		let subscriptionId: string;

		test.beforeAll( async ( { customerPage } ) => {
			// Delete existing subscriptions customer if exists to ensure clean state
			try {
				await qit.wp(
					`user delete $( wp user get ${ customerBillingConfig.email } --field=ID 2>/dev/null ) --yes 2>/dev/null || true`,
					true
				);
			} catch ( e ) {
				// User might not exist, continue
			}

			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[ [ config.products.subscription_signup_fee, 1 ] ],
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

			// Get the subscription ID
			const subscriptionLink = customerPage.getByRole( 'link', {
				name: 'View subscription number',
			} );
			const linkText = await subscriptionLink.textContent();
			subscriptionId = linkText?.trim().replace( '#', '' ) ?? '';
		} );

		test( 'should be able to renew a subscription in my account', async ( {
			adminPage,
		} ) => {
			await goToSubscriptionPage( adminPage, subscriptionId );
			await expect(
				adminPage.getByRole( 'heading', {
					name: 'Edit Subscription',
				} )
			).toBeVisible();
			const orderActions = adminPage.locator(
				'select[name="wc_order_action"]'
			);
			await orderActions.selectOption( { label: 'Process renewal' } );

			// Prepare to accept the dialog before clicking the submit button.
			// Since the page will change, we don't need to remove the listener.
			adminPage.on( 'dialog', async ( dialog ) => {
				await dialog.accept();
			} );
			await adminPage
				.locator( '#actions' )
				.getByRole( 'button', { name: /Apply.+/i } )
				.click();
			await adminPage.waitForLoadState( 'networkidle' );

			// Check if a new order is present in related orders
			await expect(
				adminPage.getByRole( 'cell', { name: 'Renewal Order' } )
			).toBeVisible();
		} );
	}
);
