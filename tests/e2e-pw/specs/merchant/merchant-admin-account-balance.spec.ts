/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

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
			await test.step(
				'Navigate to the Payments Overview screen',
				async () => {
					await page.goto(
						'/wp-admin/admin.php?page=wc-admin&path=/payments/overview'
					);
				}
			);

			await test.step(
				'Observe the total account balance, ensuring it has a formatted currency value',
				async () => {
					const totalBalanceValue = page.getByLabel(
						'Total balance',
						{
							exact: true,
						}
					);

					await expect( totalBalanceValue ).toHaveText(
						formattedCurrencyRegex
					);
				}
			);

			await test.step(
				'Observe the available account balance, ensuring it has a formatted currency value',
				async () => {
					const availableFundsValue = page.getByLabel(
						'Available funds',
						{
							exact: true,
						}
					);

					await expect( availableFundsValue ).toHaveText(
						formattedCurrencyRegex
					);
				}
			);
		}
	);

	test(
		'View the total and available account balance for multiple deposit currencies',
		{
			tag: [ '@critical', '@todo' ],
			annotation: [
				{
					type: 'issue',
					description:
						'https://github.com/Automattic/woocommerce-payments/issues/9188',
				},
				{
					type: 'description',
					description:
						'Test requirements not yet met: A merchant account with multiple deposit currencies must be available in our e2e environment',
				},
			],
		},
		async () => {
			await test.step( 'Navigate to the Payments Overview screen', () => {
				// @todo
			} );

			await test.step(
				'Select a deposit currency using the currency select input',
				async () => {
					// @todo
				}
			);

			await test.step(
				'Observe the total account balance for the selected currency, ensuring it is correctly formatted with the currency symbol',
				async () => {
					// @todo
				}
			);

			await test.step(
				'Observe the available account balance the selected currency, ensuring it is correctly formatted with the currency symbol',
				async () => {
					// @todo
				}
			);

			await test.step(
				'Select a second deposit currency using the currency select input',
				async () => {
					// @todo
				}
			);

			await test.step(
				'Observe the total account balance for the selected currency, ensuring it is correctly formatted with the currency symbol',
				async () => {
					// @todo
				}
			);

			await test.step(
				'Observe the available account balance the selected currency, ensuring it is correctly formatted with the currency symbol',
				async () => {
					// @todo
				}
			);
		}
	);
} );
