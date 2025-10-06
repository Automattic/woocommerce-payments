/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import {
	activateMulticurrency,
	addCurrency,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	removeCurrency,
	restoreCurrencies,
	setCurrencyCharmPricing,
	setCurrencyPriceRounding,
	setCurrencyRate,
} from '../../../utils/merchant';
import { goToShop } from '../../../utils/shopper-navigation';
import { getPriceFromProduct } from '../../../utils/shopper';

test.describe(
	'Multi-currency setup',
	{ tag: [ '@merchant', '@critical' ] },
	() => {
		let wasMulticurrencyEnabled: boolean;

		test.beforeAll( async ( { adminPage } ) => {
			wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
		} );

		test.afterAll( async ( { adminPage } ) => {
			await restoreCurrencies( adminPage );

			if ( ! wasMulticurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test( 'can disable the multi-currency feature', async ( {
			adminPage,
		} ) => {
			await deactivateMulticurrency( adminPage );
		} );

		test( 'can enable the multi-currency feature', async ( {
			adminPage,
		} ) => {
			await activateMulticurrency( adminPage );
		} );

		test.describe( 'Currency management', () => {
			const testCurrency = 'CHF';

			test( 'can add a new currency', async ( { adminPage } ) => {
				await addCurrency( adminPage, testCurrency );
			} );

			test( 'can remove a currency', async ( { adminPage } ) => {
				await removeCurrency( adminPage, testCurrency );
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

			test.beforeAll( async ( { adminPage, customerPage } ) => {
				await disableAllEnabledCurrencies( adminPage );
				await goToShop( customerPage, { currency: 'USD' } );

				beanieRegularPrice = await getPriceFromProduct(
					customerPage,
					'beanie'
				);
			} );

			test.beforeEach( async ( { adminPage } ) => {
				await addCurrency( adminPage, testData.currencyCode );
			} );

			test.afterEach( async ( { adminPage } ) => {
				await removeCurrency( adminPage, testData.currencyCode );
			} );

			test( 'can change the currency rate manually', async ( {
				adminPage,
				customerPage,
			} ) => {
				await setCurrencyRate(
					adminPage,
					testData.currencyCode,
					testData.rate
				);
				await setCurrencyPriceRounding(
					adminPage,
					testData.currencyCode,
					'0'
				);
				await goToShop( customerPage, {
					currency: testData.currencyCode,
				} );

				const beaniePriceOnCurrency = await getPriceFromProduct(
					customerPage,
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

			test( 'can change the charm price manually', async ( {
				adminPage,
				customerPage,
			} ) => {
				await setCurrencyRate(
					adminPage,
					testData.currencyCode,
					'1.00'
				);
				await setCurrencyPriceRounding(
					adminPage,
					testData.currencyCode,
					'0'
				);
				await setCurrencyCharmPricing(
					adminPage,
					testData.currencyCode,
					testData.charmPricing
				);
				await goToShop( customerPage, {
					currency: testData.currencyCode,
				} );

				const beaniePriceOnCurrency = await getPriceFromProduct(
					customerPage,
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

			test( 'can change the rounding precision manually', async ( {
				adminPage,
				customerPage,
			} ) => {
				const rateForTest = '1.20';

				await setCurrencyRate(
					adminPage,
					testData.currencyCode,
					rateForTest
				);
				await setCurrencyPriceRounding(
					adminPage,
					testData.currencyCode,
					testData.rounding
				);

				await goToShop( customerPage, {
					currency: testData.currencyCode,
				} );

				const beaniePriceOnCurrency = await getPriceFromProduct(
					customerPage,
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

			test.beforeAll( async ( { adminPage } ) => {
				for ( const currency of Object.keys( currencyDecimalMap ) ) {
					await addCurrency( adminPage, currency );
				}
			} );

			Object.keys( currencyDecimalMap ).forEach( ( currency: string ) => {
				test( `the decimal points for ${ currency } are displayed correctly`, async ( {
					customerPage,
				} ) => {
					await goToShop( customerPage, { currency } );

					const beaniePriceOnCurrency = await getPriceFromProduct(
						customerPage,
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
	}
);
