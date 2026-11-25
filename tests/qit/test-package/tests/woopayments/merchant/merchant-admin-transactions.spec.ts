/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import { goToTransactions } from '../../../utils/merchant';

// Preserve the legacy subscriptions test guard since QIT utils don't export this constant yet
const shouldRunSubscriptionsTests =
	process.env.SKIP_WC_SUBSCRIPTIONS_TESTS !== '1';

test.describe( 'Admin transactions', { tag: '@merchant' }, () => {
	test( 'page should load without errors', async ( { adminPage } ) => {
		await goToTransactions( adminPage );
		await expect(
			adminPage
				.getByLabel( 'Transactions', { exact: true } )
				.getByRole( 'heading', { name: 'Transactions' } )
		).toBeVisible();

		if ( shouldRunSubscriptionsTests ) {
			await expect(
				adminPage.getByRole( 'columnheader', {
					name: 'Subscription number',
				} )
			).toBeVisible();
		}

		// TODO: Uncomment this line after fixing the screenshot issue.
		// await expect( adminPage ).toHaveScreenshot();
	} );
} );
