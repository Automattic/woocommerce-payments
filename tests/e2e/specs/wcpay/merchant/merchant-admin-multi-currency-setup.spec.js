/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import {
	getProductPriceFromProductPage,
	merchantWCP,
	shopperWCP,
} from '../../../utils';

let wasMulticurrencyEnabled;

describe( 'Merchant Multi-Currency Settings', () => {
	beforeAll( async () => {
		await merchant.login();
		// Get initial multi-currency feature status.
		await merchantWCP.openWCPSettings();
		await page.waitForSelector( "[data-testid='multi-currency-toggle']" );
		wasMulticurrencyEnabled = await page.evaluate( () => {
			const checkbox = document.querySelector(
				"[data-testid='multi-currency-toggle']"
			);
			return checkbox ? checkbox.checked : false;
		} );
	} );

	afterAll( async () => {
		// Disable multi-currency if it was not initially enabled.
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.login();
			await merchantWCP.deactivateMulticurrency();
		}
		await merchant.logout();
	} );

	it( 'can enable multi-currency feature', async () => {
		// Assertions are in the merchantWCP.wcpSettingsSaveChanges() flow.
		await merchantWCP.activateMulticurrency();
	} );

	it( 'can disable multi-currency feature', async () => {
		// Assertions are in the merchantWCP.wcpSettingsSaveChanges() flow.
		await merchantWCP.deactivateMulticurrency();
	} );

	describe( 'Currency Management', () => {
		const testCurrency = 'CHF';

		beforeAll( async () => {
			await merchantWCP.activateMulticurrency();
		} );

		it( 'can add a new currency', async () => {
			await merchantWCP.addCurrency( testCurrency );
		} );

		it( 'can remove a currency', async () => {
			await merchantWCP.removeCurrency( testCurrency );
		} );
	} );

	describe( 'Currency Settings', () => {
		const testData = {
			currencyCode: 'CHF',
			rate: '1.25',
			charmPricing: '-0.01',
			rounding: '0.5',
			currencyPrecision: 2,
		};

		let beanieRegularPrice;

		beforeAll( async () => {
			await merchantWCP.activateMulticurrency();
			await merchantWCP.disableAllEnabledCurrencies();

			await shopperWCP.goToShopWithCurrency( 'USD' );
			await shopperWCP.goToProductPageBySlug( 'beanie' );
			beanieRegularPrice = await getProductPriceFromProductPage();
		} );

		beforeEach( async () => {
			await merchantWCP.openMultiCurrency();
			await merchantWCP.addCurrency( testData.currencyCode );
		} );

		afterEach( async () => {
			await merchantWCP.openMultiCurrency();
			await merchantWCP.removeCurrency( testData.currencyCode );
		} );

		it( 'can change the currency rate manually', async () => {
			await merchantWCP.setCurrencyRate(
				testData.currencyCode,
				testData.rate
			);
			await merchantWCP.setCurrencyPriceRounding(
				testData.currencyCode,
				'0'
			);

			await shopperWCP.goToShopWithCurrency( testData.currencyCode );
			await shopperWCP.goToProductPageBySlug( 'beanie' );
			const beaniePriceOnCurrency = await getProductPriceFromProductPage();

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toBe(
				( parseFloat( beanieRegularPrice ) * testData.rate ).toFixed(
					testData.currencyPrecision
				)
			);
		} );

		it( 'can change the charm price manually', async () => {
			await merchantWCP.setCurrencyRate( testData.currencyCode, '1.00' );
			await merchantWCP.setCurrencyPriceRounding(
				testData.currencyCode,
				'0'
			);
			await merchantWCP.setCurrencyCharmPricing(
				testData.currencyCode,
				testData.charmPricing
			);

			await shopperWCP.goToShopWithCurrency( testData.currencyCode );
			await shopperWCP.goToProductPageBySlug( 'beanie' );
			const beaniePriceOnCurrency = await getProductPriceFromProductPage();

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toBe(
				(
					parseFloat( beanieRegularPrice ) +
					parseFloat( testData.charmPricing )
				).toFixed( testData.currencyPrecision )
			);
		} );

		it( 'can change the rounding precision manually', async () => {
			const rateForTest = 1.2;

			await merchantWCP.setCurrencyRate(
				testData.currencyCode,
				rateForTest.toString()
			);
			await merchantWCP.setCurrencyPriceRounding(
				testData.currencyCode,
				testData.rounding
			);

			await shopperWCP.goToShopWithCurrency( testData.currencyCode );
			await shopperWCP.goToProductPageBySlug( 'beanie' );
			const beaniePriceOnCurrency = await getProductPriceFromProductPage();

			expect(
				parseFloat( beaniePriceOnCurrency ).toFixed(
					testData.currencyPrecision
				)
			).toBe(
				(
					Math.ceil(
						parseFloat( beanieRegularPrice ) *
							rateForTest *
							( 1 / testData.rounding )
					) * testData.rounding
				).toFixed( testData.currencyPrecision )
			);
		} );
	} );

	describe( 'Currency decimal points', () => {
		const currencyDecimalMap = {
			JPY: 0,
			GBP: 2,
		};

		beforeAll( async () => {
			await merchantWCP.activateMulticurrency();

			for ( const currency of Object.keys( currencyDecimalMap ) ) {
				await merchantWCP.addCurrency( currency );
			}
		} );

		it.each( Object.keys( currencyDecimalMap ) )(
			'sees the correct decimal points for %s',
			async ( currency ) => {
				await shopperWCP.goToShopWithCurrency( currency );
				await shopperWCP.goToProductPageBySlug( 'beanie' );
				const priceOnCurrency = await getProductPriceFromProductPage();

				const decimalPart = priceOnCurrency.split( '.' )[ 1 ] || '';
				expect( decimalPart.length ).toBe(
					currencyDecimalMap[ currency ]
				);
			}
		);
	} );
} );
