/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import {
	activateMulticurrency,
	activateTheme,
	addCurrency,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	getActiveThemeSlug,
	removeCurrency,
	restoreCurrencies,
	goToMultiCurrencyOnboarding,
	goToMultiCurrencySettings,
} from '../../../utils/merchant';

test.describe(
	'Multi-currency on-boarding',
	{ tag: [ '@merchant', '@critical' ] },
	() => {
		let wasMulticurrencyEnabled: boolean;
		let activeThemeSlug: string;
		const goToNextOnboardingStep = async ( adminPage ) => {
			await adminPage
				.locator( '.wcpay-wizard-task.is-active button.is-primary' )
				.click();
		};

		test.beforeAll( async ( { adminPage } ) => {
			wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
			activeThemeSlug = await getActiveThemeSlug();
		} );

		test.afterAll( async ( { adminPage } ) => {
			// Restore original theme.
			await activateTheme( activeThemeSlug );
			await restoreCurrencies( adminPage );
			if ( ! wasMulticurrencyEnabled ) {
				await deactivateMulticurrency( adminPage );
			}
		} );

		test.describe( 'Currency selection and management', () => {
			test.beforeAll( async ( { adminPage } ) => {
				await disableAllEnabledCurrencies( adminPage );
			} );

			test.beforeEach( async ( { adminPage } ) => {
				await goToMultiCurrencyOnboarding( adminPage );
			} );

			test( 'should disable the submit button when no currencies are selected', async ( {
				adminPage,
			} ) => {
				// To take a better screenshot of the component.
				await adminPage.setViewportSize( {
					width: 1280,
					height: 2000,
				} );
				// TODO: fix flaky visual regression test.
				// await expect(
				// 	adminPage.locator(
				// 		'.multi-currency-setup-wizard > div > .components-card-body'
				// 	)
				// ).toHaveScreenshot();
				// Set the viewport back to the default size.
				await adminPage.setViewportSize( { width: 1280, height: 720 } );

				const checkboxes = await adminPage
					.locator(
						'li.enabled-currency-checkbox .components-checkbox-control__input'
					)
					.all();

				for ( const checkbox of checkboxes ) {
					await checkbox.uncheck();
				}

				await expect(
					adminPage.getByRole( 'button', { name: 'Add currencies' } )
				).toBeDisabled();
			} );

			test( 'should allow multiple currencies to be selected', async ( {
				adminPage,
			} ) => {
				const currenciesNotInRecommendedList = await adminPage
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

			test( 'should exclude already enabled currencies from the onboarding', async ( {
				adminPage,
			} ) => {
				await addCurrency( adminPage, 'GBP' );
				await goToMultiCurrencyOnboarding( adminPage );

				const recommendedCurrencies = await adminPage
					.getByTestId( 'recommended-currency' )
					.allTextContents();

				for ( const currency of recommendedCurrencies ) {
					expect( currency ).not.toMatch( /GBP/ );
				}

				await removeCurrency( adminPage, 'GBP' );
			} );

			test( 'should display suggested currencies at the beginning of the list', async ( {
				adminPage,
			} ) => {
				await expect(
					adminPage.getByTestId( 'recommended-currency' )
				).not.toHaveCount( 0 );
			} );

			test( 'selected currencies are enabled after onboarding', async ( {
				adminPage,
			} ) => {
				const currencyCodes = [ 'GBP', 'EUR', 'CAD', 'AUD' ];

				for ( const currencyCode of currencyCodes ) {
					await adminPage
						.locator(
							`input[type="checkbox"][code="${ currencyCode }"]`
						)
						.check();
				}

				await goToNextOnboardingStep( adminPage );
				await goToMultiCurrencySettings( adminPage );

				// Ensure the currencies are enabled.
				for ( const currencyCode of currencyCodes ) {
					await expect(
						adminPage.locator(
							`li.enabled-currency.${ currencyCode.toLowerCase() }`
						)
					).toBeVisible();
				}
			} );
		} );

		test.describe( 'Geolocation features', () => {
			test( 'should offer currency switch by geolocation', async ( {
				adminPage,
			} ) => {
				await goToMultiCurrencyOnboarding( adminPage );
				await goToNextOnboardingStep( adminPage );
				await adminPage.getByTestId( 'enable_auto_currency' ).check();
				await expect(
					adminPage.getByTestId( 'enable_auto_currency' )
				).toBeChecked();
			} );

			test( 'should preview currency switch by geolocation correctly with USD and GBP', async ( {
				adminPage,
			} ) => {
				await addCurrency( adminPage, 'GBP' );
				await goToMultiCurrencyOnboarding( adminPage );
				// To take a better screenshot of the iframe preview.
				await adminPage.setViewportSize( {
					width: 1280,
					height: 1280,
				} );
				await goToNextOnboardingStep( adminPage );
				// TODO: fix flaky visual regression test.
				// await expect(
				// 	adminPage.locator( '.wcpay-wizard-task.is-active' )
				// ).toHaveScreenshot();
				await adminPage.getByTestId( 'enable_auto_currency' ).check();
				await adminPage
					.getByRole( 'button', { name: 'Preview' } )
					.click();

				const previewIframe = await adminPage.locator(
					'.multi-currency-store-settings-preview-iframe'
				);

				await expect( previewIframe ).toBeVisible();

				const previewPage = previewIframe.contentFrame();

				await expect(
					await previewPage.locator( '.woocommerce-store-notice' )
				).toBeVisible();
				// TODO: fix flaky visual regression test.
				// await expect(
				// 	adminPage.locator( '.multi-currency-store-settings-preview-iframe' )
				// ).toHaveScreenshot();

				const noticeText = await previewPage
					.locator( '.woocommerce-store-notice' )
					.innerText();

				expect( noticeText ).toContain(
					"We noticed you're visiting from United Kingdom (UK). We've updated our prices to Pound sterling for your shopping convenience."
				);
			} );
		} );

		test.describe( 'Currency Switcher widget', () => {
			test( 'should offer the currency switcher widget while Storefront theme is active', async ( {
				adminPage,
			} ) => {
				await activateTheme( 'storefront' );
				await goToMultiCurrencyOnboarding( adminPage );
				await goToNextOnboardingStep( adminPage );

				const storefrontSwitcher = adminPage.getByTestId(
					'enable_storefront_switcher'
				);
				await storefrontSwitcher.check();
				await expect( storefrontSwitcher ).toBeChecked();
			} );

			test( 'should not offer the currency switcher widget when an unsupported theme is active', async ( {
				adminPage,
			} ) => {
				await activateTheme( 'twentytwentyfour' );
				await goToMultiCurrencyOnboarding( adminPage );
				await goToNextOnboardingStep( adminPage );

				// The switcher should be hidden for unsupported themes
				const storefrontSwitcher = adminPage.getByTestId(
					'enable_storefront_switcher'
				);
				await expect( storefrontSwitcher ).toBeHidden();

				await activateTheme( 'storefront' );
			} );
		} );
	}
);
