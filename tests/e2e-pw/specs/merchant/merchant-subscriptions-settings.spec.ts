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
			expect(
				page.locator( 'a.nav-tab-active', {
					hasText: 'Subscriptions',
				} )
			).toBeVisible();
		} );
	}
);
