/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../../utils/helpers';

// Optional currency symbol, followed by one or more digits, decimal separator, or comma.
const formattedCurrencyRegex = /[^\d.,]*[\d.,]+/;

test.describe( 'Merchant account balance overview', () => {
	// Use the merchant user for this test suite.
	useMerchant();

	test(
		'View the total and available account balance for a single deposit currency',
		{
			tag: '@critical',
		},
		async ( { page } ) => {
			await test.step( 'Navigate to the Payments Overview screen', async () => {
				await page.goto(
					'/wp-admin/admin.php?page=wc-admin&path=/payments/overview'
				);
			} );

			await test.step( 'Observe the total account balance, ensuring it has a formatted currency value', async () => {
				const totalBalanceValue = page.getByLabel( 'Total balance', {
					exact: true,
				} );

				await expect( totalBalanceValue ).toHaveText(
					formattedCurrencyRegex
				);
			} );

			await test.step( 'Observe the available account balance, ensuring it has a formatted currency value', async () => {
				const availableFundsValue = page.getByLabel(
					'Available funds',
					{
						exact: true,
					}
				);

				await expect( availableFundsValue ).toHaveText(
					formattedCurrencyRegex
				);
			} );
		}
	);

	// Multi-deposit-currency coverage tracked in
	// https://github.com/Automattic/woocommerce-payments/issues/9188 — blocked
	// on test-environment fixture work. Re-add the spec when a merchant
	// account with multiple deposit currencies is available in CI.
} );
