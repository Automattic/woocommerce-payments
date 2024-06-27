/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

test.describe( 'Merchant Account Balance Overview', () => {
	// Use the merchant user for this test suite.
	useMerchant();

	test(
		'View the total and available account balance for a single deposit currency (USD)',
		{
			tag: '@critical',
		},
		async ( { page } ) => {
			await test.step(
				'Navigate to the Payments Overview screen',
				async () => {
					await page.goto(
						'/wp-admin/admin.php?page=wc-admin&path=/payments/overview'
					);
				}
			);

			await test.step(
				'Observe the total account balance, ensuring it has a dollar value with the correct currency formatting',
				async () => {
					const totalBalanceValue = page.getByLabel(
						'Total balance',
						{
							exact: true, // true without exact:true to test debugging
						}
					);

					// To demonstrate auto-wait, try:
					expect( totalBalanceValue ).toHaveText( /\$\d+/ );

					// Match the total balance value to the USD format $1*
					await expect( totalBalanceValue ).toHaveText( /\$\d+/ );
				}
			);

			await test.step(
				'Observe the available account balance, ensuring it has a dollar value with the correct currency formatting',
				async () => {
					const availableFundsValue = page.getByLabel(
						'Available funds',
						{
							exact: true,
						}
					);
					await expect( availableFundsValue ).toHaveText( /\$\d+/ );
				}
			);
		}
	);

	test(
		'View the total and available account balance for multiple deposit currencies',
		{
			tag: '@critical @todo',
		},
		async () => {
			// @todo Implement this test
		}
	);
} );
