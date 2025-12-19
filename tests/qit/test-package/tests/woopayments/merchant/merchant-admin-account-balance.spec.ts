/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { goToPaymentsOverview, dataHasLoaded } from '../../../utils/merchant';

// Optional currency symbol, followed by one or more digits, decimal separator, or comma.
const formattedCurrencyRegex = /[^\d.,]*[\d.,]+/;

test.describe(
	'Merchant account balance overview',
	{ tag: '@merchant' },
	() => {
		test(
			'View the total and available account balance for a single deposit currency',
			{
				tag: '@critical',
			},
			async ( { adminPage } ) => {
				await test.step(
					'Navigate to the Payments Overview screen',
					async () => {
						await goToPaymentsOverview( adminPage );
						await dataHasLoaded( adminPage );
					}
				);

				await test.step(
					'Observe the total account balance, ensuring it has a formatted currency value',
					async () => {
						const totalBalanceValue = adminPage.getByLabel(
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
						const availableFundsValue = adminPage.getByLabel(
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

		// @critical tag intentionally omitted until test is fully implemented.
		// Unimplemented tests should not be marked critical as they provide false confidence in coverage.
		test(
			'View the total and available account balance for multiple deposit currencies',
			{
				tag: '@todo',
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
				await test.step(
					'Navigate to the Payments Overview screen',
					() => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Select a deposit currency using the currency select input',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Observe the total account balance for the selected currency, ensuring it is correctly formatted with the currency symbol',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Observe the available account balance the selected currency, ensuring it is correctly formatted with the currency symbol',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Select a second deposit currency using the currency select input',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Observe the total account balance for the selected currency, ensuring it is correctly formatted with the currency symbol',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);

				await test.step(
					'Observe the available account balance the selected currency, ensuring it is correctly formatted with the currency symbol',
					async () => {
						// @todo https://github.com/Automattic/woocommerce-payments/issues/9188
					}
				);
			}
		);
	}
);
