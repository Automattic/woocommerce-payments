/**
 * External dependencies
 */
import test, { expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { shouldRunSubscriptionsTests } from '../../utils/constants';
import { getMerchant } from '../../utils/helpers';
import { goToTransactions } from '../../utils/merchant-navigation';

test.describe( 'Admin transactions', () => {
	test( 'page should load without errors', async ( { browser } ) => {
		const { merchantPage } = await getMerchant( browser );
		await goToTransactions( merchantPage );
		await expect(
			merchantPage
				.getByLabel( 'Transactions', { exact: true } )
				.getByRole( 'heading', { name: 'Transactions' } )
		).toBeVisible();

		if ( shouldRunSubscriptionsTests ) {
			await expect(
				merchantPage.getByRole( 'columnheader', {
					name: 'Subscription number',
				} )
			).toBeVisible();
		}

		await expect( merchantPage ).toHaveScreenshot();
	} );
} );
