/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant, describeif } from '../../utils/helpers';
import { addCartProduct, placeOrder, setupCheckout } from '../../utils/shopper';
import { config } from '../../config/default';
import {
	shouldRunActionSchedulerTests,
	shouldRunSubscriptionsTests,
} from '../../utils/constants';

// Run the tests if the two 'skip' environment variables are not set.
describeif( shouldRunSubscriptionsTests && shouldRunActionSchedulerTests )(
	'Subscriptions > Renew a subscription via Action Scheduler',
	() => {
		useMerchant();

		test.beforeEach( async ( { page } ) => {
			await addCartProduct( page, 88 ); // Subscription no signup fee product
			await setupCheckout(
				page,
				config.addresses[ 'subscriptions-customer' ].billing
			);
			await placeOrder( page );
		} );

		test( 'should renew a subscription with action scheduler', async ( {
			page,
		} ) => {
			// WIP: This test is not yet implemented.
			// To keep the linter happy for now:
			expect( page.url() ).toContain( '/checkout/' );
		} );
	}
);
