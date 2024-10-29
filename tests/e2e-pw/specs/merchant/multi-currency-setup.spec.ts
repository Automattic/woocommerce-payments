/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { getMerchant, getShopper } from '../../utils/helpers';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	removeCurrency,
	setCurrencyCharmPricing,
	setCurrencyPriceRounding,
	setCurrencyRate,
} from '../../utils/merchant';
import * as navigation from '../../utils/shopper-navigation';
import { getPriceFromProduct } from '../../utils/shopper';

test.describe( 'Multi-currency setup', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );
	} );

	test.afterAll( async () => {
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}
	} );

	test( 'can disable the multi-currency feature', async () => {
		await deactivateMulticurrency( merchantPage );
	} );

	test( 'can enable the multi-currency feature', async () => {
		await activateMulticurrency( merchantPage );
	} );

	test.describe( 'Currency management', () => {
		const testCurrency = 'CHF';

		test( 'can add a new currency', async () => {
			await addCurrency( merchantPage, testCurrency );
		} );

		test( 'can remove a currency', async () => {
			await removeCurrency( merchantPage, testCurrency );
		} );
	} );

	test.describe( 'Currency settings', () => {
		let beanieRegularPrice: string;
		const testData = {
			currencyCode: 'CHF',
			rate: '1.25',
			charmPricing: '-0.01',
			rounding: '0.5',
			currencyPrecision: 2,
		};

		test.beforeAll( async () => {
			await disableAllEnabledCurrencies( merchantPage );
			await navigation.goToShopWithCurrency( shopperPage, 'USD' );

			beanieRegularPrice = await getPriceFromProduct(
				shopperPage,
				'beanie'
			);
		} );

		test.beforeEach( async () => {
			await addCurrency( merchantPage, testData.currencyCode );
		} );

		test.afterEach( async () => {
			await removeCurrency( merchantPage, testData.currencyCode );
		} );

		test( 'can change the currency rate manually', async () => {
			await setCurrencyRate(
				merchantPage,
				testData.currencyCode,
				testData.rate
			);
			await setCurrencyPriceRounding(
				merchantPage,
				testData.currencyCode,
				'0'
			);
			await navigation.goToShopWithCurrency(
				shopperPage,
				testData.currencyCode
			);

			const beaniePriceOnCurrency = await getPriceFromProduct(
				shopperPage,
				'beanie'
			);

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toEqual(
				(
					parseFloat( beanieRegularPrice ) *
					parseFloat( testData.rate )
				).toFixed( testData.currencyPrecision )
			);
		} );

		test( 'can change the charm price manually', async () => {
			await setCurrencyRate(
				merchantPage,
				testData.currencyCode,
				'1.00'
			);
			await setCurrencyPriceRounding(
				merchantPage,
				testData.currencyCode,
				'0'
			);
			await setCurrencyCharmPricing(
				merchantPage,
				testData.currencyCode,
				testData.charmPricing
			);
			await navigation.goToShopWithCurrency(
				shopperPage,
				testData.currencyCode
			);

			const beaniePriceOnCurrency = await getPriceFromProduct(
				shopperPage,
				'beanie'
			);

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toEqual(
				(
					parseFloat( beanieRegularPrice ) +
					parseFloat( testData.charmPricing )
				).toFixed( testData.currencyPrecision )
			);
		} );

		test( 'can change the rounding precision manually', async () => {
			const rateForTest = '1.20';

			await setCurrencyRate(
				merchantPage,
				testData.currencyCode,
				rateForTest
			);
			await setCurrencyPriceRounding(
				merchantPage,
				testData.currencyCode,
				testData.rounding
			);

			const beaniePriceOnCurrency = await getPriceFromProduct(
				shopperPage,
				'beanie'
			);

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toEqual(
				(
					Math.ceil(
						parseFloat( beanieRegularPrice ) *
							parseFloat( rateForTest ) *
							( 1 / parseFloat( testData.rounding ) )
					) * parseFloat( testData.rounding )
				).toFixed( testData.currencyPrecision )
			);
		} );
	} );

	test.describe( 'Currency decimal points', () => {
		const currencyDecimalMap = {
			JPY: 0,
			GBP: 2,
		};

		test.beforeAll( async () => {
			for ( const currency of Object.keys( currencyDecimalMap ) ) {
				await addCurrency( merchantPage, currency );
			}
		} );

		Object.keys( currencyDecimalMap ).forEach( ( currency: string ) => {
			test( `the decimal points for ${ currency } are displayed correctly`, async () => {
				await navigation.goToShopWithCurrency( shopperPage, currency );

				const beaniePriceOnCurrency = await getPriceFromProduct(
					shopperPage,
					'beanie'
				);
				const decimalPart =
					beaniePriceOnCurrency.split( '.' )[ 1 ] || '';

				expect( decimalPart.length ).toEqual(
					currencyDecimalMap[ currency ]
				);
			} );
		} );
	} );
} );
