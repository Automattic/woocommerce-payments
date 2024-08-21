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

test.describe( 'Multi-currency on-boarding', () => {
	let page: Page;
	let wasMulticurrencyEnabled: boolean;
	let activeThemeSlug: string;
	const goToNextOnboardingStep = async ( currentPage: Page ) => {
		await currentPage
			.locator( '.wcpay-wizard-task.is-active button.is-primary' )
			.click();
	};

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
			// To take a better screenshot of the component.
			await page.setViewportSize( { width: 1280, height: 2000 } );
			await expect(
				page.locator(
					'.multi-currency-setup-wizard > div > .components-card-body'
				)
			).toHaveScreenshot();
			// Set the viewport back to the default size.
			await page.setViewportSize( { width: 1280, height: 720 } );

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

			await goToNextOnboardingStep( page );
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

	test.describe( 'Geolocation features', () => {
		test( 'should offer currency switch by geolocation', async () => {
			await navigation.goToMultiCurrencyOnboarding( page );
			await goToNextOnboardingStep( page );
			await page.getByTestId( 'enable_auto_currency' ).check();
			await expect(
				page.getByTestId( 'enable_auto_currency' )
			).toBeChecked();
		} );

		test( 'should preview currency switch by geolocation correctly with USD and GBP', async () => {
			await addCurrency( page, 'GBP' );
			await navigation.goToMultiCurrencyOnboarding( page );
			// To take a better screenshot of the iframe preview.
			await page.setViewportSize( { width: 1280, height: 1280 } );
			await goToNextOnboardingStep( page );
			await expect(
				page.locator( '.wcpay-wizard-task.is-active' )
			).toHaveScreenshot();
			await page.getByTestId( 'enable_auto_currency' ).check();
			await page.getByRole( 'button', { name: 'Preview' } ).click();

			const previewIframe = await page.locator(
				'.multi-currency-store-settings-preview-iframe'
			);

			await expect( previewIframe ).toBeVisible();

			const previewPage = previewIframe.contentFrame();

			await expect(
				await previewPage.locator( '.woocommerce-store-notice' )
			).toBeVisible();
			await expect(
				page.locator( '.multi-currency-store-settings-preview-iframe' )
			).toHaveScreenshot();

			const noticeText = await previewPage
				.locator( '.woocommerce-store-notice' )
				.innerText();

			expect( noticeText ).toContain(
				"We noticed you're visiting from United Kingdom (UK). We've updated our prices to Pound sterling for your shopping convenience."
			);
		} );
	} );

	test.describe( 'Currency Switcher widget', () => {
		test( 'should offer the currency switcher widget while Storefront theme is active', async () => {
			await activateTheme( page, 'storefront' );
			await navigation.goToMultiCurrencyOnboarding( page );
			await goToNextOnboardingStep( page );
			await page.getByTestId( 'enable_storefront_switcher' ).check();
			await expect(
				page.getByTestId( 'enable_storefront_switcher' )
			).toBeChecked();
		} );

		test( 'should not offer the currency switcher widget when an unsupported theme is active', async () => {
			await activateTheme( page, 'twentytwentyfour' );
			await navigation.goToMultiCurrencyOnboarding( page );
			await goToNextOnboardingStep( page );
			await expect(
				page.getByTestId( 'enable_storefront_switcher' )
			).toBeHidden();
			await activateTheme( page, 'storefront' );
		} );
	} );
} );
