/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { useMerchant } from '../../utils/helpers';
import {
	activateMulticurrency,
	activateTheme,
	addCurrency,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	getActiveThemeSlug,
	removeCurrency,
} from '../../utils/merchant';
import * as navigation from '../../utils/merchant-navigation';

const GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR =
	'input[data-testid="enable_auto_currency"]';
const PREVIEW_STORE_BTN_SELECTOR = '.multi-currency-setup-preview-button';
const PREVIEW_STORE_IFRAME_SELECTOR =
	'.multi-currency-store-settings-preview-iframe';
const STOREFRONT_SWITCH_CHECKBOX_SELECTOR =
	'input[data-testid="enable_storefront_switcher"]';

test.describe( 'Merchant On-boarding', () => {
	let page: Page;
	let wasMulticurrencyEnabled: boolean;
	let activeThemeSlug: string;

	useMerchant();

	test.beforeAll( async ( { browser } ) => {
		page = await browser.newPage();
		wasMulticurrencyEnabled = await activateMulticurrency( page );
		activeThemeSlug = await getActiveThemeSlug( page );
	} );

	test.afterAll( async () => {
		// Restore original theme.
		await activateTheme( page, activeThemeSlug );

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( page );
		}

		await page.close();
	} );

	test.describe( 'Currency selection and management', () => {
		test.beforeAll( async () => {
			await disableAllEnabledCurrencies( page );
		} );

		test.beforeEach( async () => {
			await navigation.goToMultiCurrencyOnboarding( page );
		} );

		test( 'should disable the submit button when no currencies are selected', async () => {
			await expect(
				page.locator( '.multi-currency-setup-wizard' )
			).toHaveScreenshot();

			const checkboxes = await page
				.locator(
					'li.enabled-currency-checkbox .components-checkbox-control__input'
				)
				.all();

			for ( const checkbox of checkboxes ) {
				await checkbox.uncheck();
			}

			await expect(
				page.getByRole( 'button', { name: 'Add currencies' } )
			).toBeDisabled();
		} );

		test( 'should allow multiple currencies to be selected', async () => {
			const currenciesNotInRecommendedList = await page
				.locator(
					'li.enabled-currency-checkbox:not([data-testid="recommended-currency"]) input[type="checkbox"]'
				)
				.all();

			// We don't need to check them all.
			const maximumCurrencies =
				currenciesNotInRecommendedList.length > 3
					? 3
					: currenciesNotInRecommendedList.length;

			for ( let i = 0; i < maximumCurrencies; i++ ) {
				await expect(
					currenciesNotInRecommendedList[ i ]
				).toBeEnabled();
				await currenciesNotInRecommendedList[ i ].check();
				await expect(
					currenciesNotInRecommendedList[ i ]
				).toBeChecked();
			}
		} );

		test( 'should exclude already enabled currencies from the onboarding', async () => {
			await addCurrency( page, 'GBP' );
			await navigation.goToMultiCurrencyOnboarding( page );

			const recommendedCurrencies = await page
				.getByTestId( 'recommended-currency' )
				.allTextContents();

			for ( const currency of recommendedCurrencies ) {
				expect( currency ).not.toMatch( /GBP/ );
			}

			await removeCurrency( page, 'GBP' );
		} );

		test( 'should display suggested currencies at the beginning of the list', async () => {
			await expect(
				( await page.getByTestId( 'recommended-currency' ).all() )
					.length
			).toBeGreaterThan( 0 );
		} );

		test( 'selected currencies are enabled after onboarding', async () => {
			const currencyCodes = [ 'GBP', 'EUR', 'CAD', 'AUD' ];

			for ( const currencyCode of currencyCodes ) {
				await page
					.locator(
						`input[type="checkbox"][code="${ currencyCode }"]`
					)
					.check();
			}

			await page
				.getByRole( 'button', { name: /Add \d currenc(y|ies)/ } )
				.click();
			await navigation.goToMultiCurrencySettings( page );

			// Ensure the currencies are enabled.
			for ( const currencyCode of currencyCodes ) {
				await expect(
					page.locator(
						`li.enabled-currency.${ currencyCode.toLowerCase() }`
					)
				).toBeVisible();
			}
		} );
	} );

	test.describe( 'Geolocation Features', () => {
		test.beforeAll( async () => {
			await disableAllEnabledCurrencies( page );
		} );

		test.beforeEach( async () => {
			await navigation.goToMultiCurrencyOnboarding( page );
		} );

		test( 'should offer currency switch by geolocation', async () => {
			await page
				.getByRole( 'button', { name: /Add \d currenc(y|ies)/ } )
				.click();

			await page.getByTestId( 'enable_auto_currency' ).check();
			await expect(
				page.getByTestId( 'enable_auto_currency' )
			).toBeChecked();
		} );

		test( 'should preview currency switch by geolocation correctly with USD and GBP', async () => {
			// To take a better screenshot of the iframe preview.
			page.setViewportSize( { width: 1280, height: 1280 } );

			// await goToNextOnboardingStep();

			// await takeScreenshot(
			// 	'merchant-on-boarding-multicurrency-screen-2'
			// );

			// // Enable feature.
			// await setCheckboxState(
			// 	GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR,
			// 	true
			// );

			// // Click preview button.
			// await page.click( PREVIEW_STORE_BTN_SELECTOR );

			// await page.waitForSelector( PREVIEW_STORE_IFRAME_SELECTOR, {
			// 	timeout: 3000,
			// } );

			// const iframeElement = await page.$( PREVIEW_STORE_IFRAME_SELECTOR );
			// const iframe = await iframeElement.contentFrame();

			// await iframe.waitForSelector( '.woocommerce-store-notice', {
			// 	timeout: 3000,
			// } );

			// await takeScreenshot(
			// 	'merchant-on-boarding-multicurrency-geolocation-switcher-preview'
			// );

			// const noticeText = await iframe.$eval(
			// 	'.woocommerce-store-notice',
			// 	( element ) => element.innerText
			// );
			// expect( noticeText ).toContain(
			// 	// eslint-disable-next-line max-len
			// 	"We noticed you're visiting from United Kingdom (UK). We've updated our prices to Pound sterling for your shopping convenience."
			// );
		} );
	} );

	test.describe( 'Currency Switcher Widget', () => {
		// it( 'Should offer the currency switcher widget while Storefront theme is active', async () => {
		// 	await activateTheme( 'storefront' );

		// 	await goToOnboardingPage();
		// 	await goToNextOnboardingStep();

		// 	const checkbox = await page.$(
		// 		STOREFRONT_SWITCH_CHECKBOX_SELECTOR
		// 	);

		// 	// Check if exists and not disabled.
		// 	expect( checkbox ).not.toBeNull();
		// 	const isDisabled = await (
		// 		await checkbox.getProperty( 'disabled' )
		// 	 ).jsonValue();
		// 	expect( isDisabled ).toBe( false );

		// 	// Click the checkbox to select it.
		// 	await page.click( STOREFRONT_SWITCH_CHECKBOX_SELECTOR );

		// 	// Check if the checkbox is selected.
		// 	const isChecked = await (
		// 		await checkbox.getProperty( 'checked' )
		// 	 ).jsonValue();
		// 	expect( isChecked ).toBe( true );
		// } );

		// it( 'Should not offer the currency switcher widget when an unsupported theme is active', async () => {
		// 	await activateTheme( 'twentytwentyfour' );

		// 	await goToOnboardingPage();
		// 	await goToNextOnboardingStep();

		// 	const checkbox = await page.$(
		// 		STOREFRONT_SWITCH_CHECKBOX_SELECTOR
		// 	);

		// 	expect( checkbox ).toBeNull();

		// 	await activateTheme( 'storefront' );
		// } );
	} );
} );
