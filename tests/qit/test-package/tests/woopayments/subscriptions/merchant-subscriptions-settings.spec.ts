/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { goToWooCommerceSettings } from '../../../utils/merchant';

test.describe(
	'WooCommerce > Settings > Subscriptions',
	{ tag: [ '@merchant', '@subscriptions' ] },
	() => {
		test( 'Merchant should be able to load WooCommerce Subscriptions settings tab', async ( {
			adminPage,
		} ) => {
			await goToWooCommerceSettings( adminPage, 'subscriptions' );
			const menuItem = adminPage.getByRole( 'main' ).getByRole( 'link', {
				name: 'Subscriptions',
				exact: true,
			} );
			await expect( menuItem ).toBeVisible();

			// An alternative way to verify the subscriptions menu page is active, avoiding the active tab classname.
			const heading = await adminPage
				.getByRole( 'heading', {
					name: 'Subscriptions',
				} )
				.first();
			await expect( heading ).toBeVisible();
		} );
	}
);
