/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

test.describe(
	'Merchant account balance overview for single deposit currency accounts',
	() => {
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

						// Match the total balance value to the USD format $1*
						// Intentionally not expecting a specific value to allow for different values in different environments.
						await expect( totalBalanceValue ).toHaveText( /\$\d+/ );

						/**
						 * Example of what to avoid: awaiting the page/element/value rather than the assertion (expect)
						 * https://playwright.dev/docs/best-practices#use-web-first-assertions
						 */
						// const totalBalanceValue = await page
						// 	.getByLabel( 'Total balance', {
						// 		exact: true, // true without exact:true to test debugging
						// 	} )
						// 	.textContent();
						// expect( totalBalanceValue ).toMatch( /\$\d+/ );

						/**
						 * Example of what to avoid: using a class name (non-user-facing implementation detail) to find the total balance value element
						 * https://playwright.dev/docs/best-practices#prefer-user-facing-attributes-to-xpath-or-css-selectors
						 */
						// const totalBalanceValueWithClassname = page
						// 	.locator(
						// 		'.wcpay-account-balances__balances__item__amount'
						// 	)
						// 	.first();
						// await expect( totalBalanceValueWithClassname ).toHaveText(
						// 	/\$\d+/
						// );
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
						await expect( availableFundsValue ).toHaveText(
							/\$\d+/
						);
					}
				);
			}
		);
	}
);

test.describe(
	'Merchant account balance overview for multiple deposit currency accounts',
	{
		tag: '@critical @todo',
	},
	() => {
		/**
		 * Test requirements not yet met:
		 * - A merchant account with multiple deposit currencies must be available in our e2e environment
		 */

		useMerchant();

		test( 'Select multiple deposit currencies and view the total and available account balance for each', async () => {
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
		} );
	}
);
