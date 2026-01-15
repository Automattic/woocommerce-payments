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
import { goToSubscriptions } from '../../../utils/shopper-navigation';
import {
	activateMulticurrency,
	deactivateMulticurrency,
	restoreCurrencies,
} from '../../../utils/merchant';

const products = {
	'Subscription no signup fee product': 'subscription-no-signup-fee-product',
	'Subscription signup fee product': 'subscription-signup-fee-product',
};
const configBillingAddress =
	config.addresses[ 'subscriptions-customer' ].billing;

test.describe(
	'Subscriptions > Purchase multiple subscriptions',
	{ tag: [ '@shopper', '@subscriptions', '@critical' ] },
	() => {
		let wasMulticurrencyEnabled = false;

		test.beforeAll( async ( { adminPage, customerPage } ) => {
			// Delete existing subscriptions customer if exists to ensure clean state
			try {
				await qit.wp(
					`user delete $( wp user get ${ configBillingAddress.email } --field=ID 2>/dev/null ) --yes 2>/dev/null || true`,
					true
				);
			} catch ( e ) {
				// User might not exist, continue
			}

			wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
			await restoreCurrencies( adminPage );
		} );

		test.afterAll( async ( { adminPage } ) => {
			if ( ! wasMulticurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test( 'should be able to purchase multiple subscriptions', async ( {
			customerPage,
		} ) => {
			// As a Shopper, purchase the subscription products.
			await emptyCart( customerPage );
			await setupProductCheckout(
				customerPage,
				[
					[ config.products.subscription_no_signup_fee, 1 ],
					[ config.products.subscription_signup_fee, 1 ],
				],
				configBillingAddress,
				'USD'
			);
			await fillCardDetails( customerPage, config.cards.basic );
			await focusPlaceOrderButton( customerPage );
			await placeOrder( customerPage );
			await expect(
				customerPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();

			const subscriptionId = (
				await customerPage
					.getByLabel( 'View subscription number' )
					.innerText()
			)
				.trim()
				.replace( '#', '' );

			await goToSubscriptions( customerPage );

			const subscriptionRow = customerPage.getByRole( 'row', {
				name: `subscription number ${ subscriptionId }`,
			} );

			await expect( subscriptionRow ).toBeVisible();
			await subscriptionRow
				.getByRole( 'link', {
					name: 'View',
				} )
				.nth( 0 )
				.click();

			await customerPage.waitForLoadState( 'networkidle' );

			// Ensure 'Subscription totals' section lists the subscription products with the correct price.
			const subTotalsRows = customerPage.locator(
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
				customerPage
					.getByRole( 'row', { name: 'total:' } )
					.getByRole( 'cell' )
					.nth( 1 )
			).toContainText( '$19.98 USD / month' );

			// Confirm related order total matches payment
			await expect(
				customerPage.getByText( '$21.97 USD for 2 items' )
			).toBeVisible();
		} );
	}
);
