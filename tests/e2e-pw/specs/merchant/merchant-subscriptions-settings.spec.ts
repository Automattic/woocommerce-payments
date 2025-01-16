/**
 * External dependencies
 */
import test, { expect } from 'playwright/test';
import { describeif, useMerchant } from '../../utils/helpers';
import { shouldRunSubscriptionsTests } from '../../utils/constants';
import { goToWooCommerceSettings } from '../../utils/merchant-navigation';

describeif( shouldRunSubscriptionsTests )(
	'WooCommerce > Settings > Subscriptions',
	() => {
		useMerchant();
		test( 'Merchant should be able to load WooCommerce Subscriptions settings tab', async ( {
			page,
		} ) => {
			await goToWooCommerceSettings( page, 'subscriptions' );
			const menuItem = page.getByRole( 'main' ).getByRole( 'link', {
				name: 'Subscriptions',
				exact: true,
			} );
			await expect( menuItem ).toBeVisible();

			// An alternative way to verify the subscriptions menu page is active, avoiding the active tab classname.
			const heading = await page.getByRole( 'heading', {
				name: 'Subscriptions',
			} );
			await expect( heading ).toBeVisible();
		} );
	}
);
