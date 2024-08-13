/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';

interface CurrencyData {
	code: string;
	symbol: string;
	symbolPosition: 'left' | 'right';
	decimalSeparator: string;
	priceFormat: string;
	thousandSeparator: string;
	precision: number;
}

declare global {
	interface Window {
		wcpaySettings: {
			accountDefaultCurrency: string;
			currencyData: Record< string, CurrencyData >;
		};
	}
}

// Regular expression to match a formatted currency value, with the currency symbol on the left or right.
const formattedCurrencyRegex = (
	symbolPosition: 'left' | 'right',
	symbol: string
) => {
	// TODO: this doesn't work for html encoded entities like CHF.
	if ( symbolPosition === 'left' ) {
		// Match a currency value with symbol on the left,
		// followed by one or more digits, decimal separator, or comma.
		// with an optional negative sign at the beginning.
		return new RegExp( `^-?${ symbol }[\\d.,]+` );
	}
	// Match a currency value with symbol on the right,
	// followed by one or more digits, decimal separator, or comma.
	// with an optional negative sign at the beginning.
	return new RegExp( `^-?[\\d.,]+${ symbol }` );
};

/**
 * Get the merchant account default currency data from window.wcpaySettings.
 */
const getDefaultAccountCurrencyData = async (
	page
): Promise< CurrencyData > => {
	return await page.evaluate( () => {
		const { accountDefaultCurrency, currencyData } = window.wcpaySettings;

		const accountDefaultCurrencyData = Object.values( currencyData ).find(
			( currency ) =>
				currency.code.toLowerCase() ===
				accountDefaultCurrency.toLowerCase()
		);

		return accountDefaultCurrencyData;
	} );
};

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

			const {
				symbolPosition,
				symbol,
			} = await getDefaultAccountCurrencyData( page );

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
						formattedCurrencyRegex( symbolPosition, symbol )
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
						formattedCurrencyRegex( symbolPosition, symbol )
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
