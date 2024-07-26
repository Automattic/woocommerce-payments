/**
 * External dependencies
 */
const { merchant, WP_ADMIN_DASHBOARD } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import {
	merchantWCP,
	setCheckboxState,
	takeScreenshot,
	uiLoaded,
} from '../../../utils';

// Shared selector constants.
const THEME_SELECTOR = ( themeSlug ) => `.theme[data-slug="${ themeSlug }"]`;
const ACTIVATE_THEME_BUTTON_SELECTOR = ( themeSlug ) =>
	`${ THEME_SELECTOR( themeSlug ) } .button.activate`;
const MULTI_CURRENCY_TOGGLE_SELECTOR = "[data-testid='multi-currency-toggle']";
const RECOMMENDED_CURRENCY_LIST_SELECTOR =
	'li[data-testid="recommended-currency"]';
const CURRENCY_NOT_IN_RECOMMENDED_LIST_SELECTOR =
	'li.enabled-currency-checkbox:not([data-testid="recommended-currency"])';
const ENABLED_CURRENCY_LIST_SELECTOR = 'li.enabled-currency-checkbox';
const GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR =
	'input[data-testid="enable_auto_currency"]';
const PREVIEW_STORE_BTN_SELECTOR = '.multi-currency-setup-preview-button';
const PREVIEW_STORE_IFRAME_SELECTOR =
	'.multi-currency-store-settings-preview-iframe';
const SUBMIT_STEP_BTN_SELECTOR =
	'.add-currencies-task.is-active .task-collapsible-body.is-active > button.is-primary';
const STOREFRONT_SWITCH_CHECKBOX_SELECTOR =
	'input[data-testid="enable_storefront_switcher"]';

let wasMulticurrencyEnabled;

const goToThemesPage = async () => {
	await page.goto( `${ WP_ADMIN_DASHBOARD }themes.php`, {
		waitUntil: 'networkidle0',
	} );
};

const activateTheme = async ( themeSlug ) => {
	await goToThemesPage();

	// Check if the theme is already active.
	const isActive = await page.evaluate( ( selector ) => {
		const themeElement = document.querySelector( selector );
		return themeElement && themeElement.classList.contains( 'active' );
	}, THEME_SELECTOR( themeSlug ) );

	// Activate the theme if it's not already active.
	if ( ! isActive ) {
		await page.click( ACTIVATE_THEME_BUTTON_SELECTOR( themeSlug ) );
		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
	}
};

const goToOnboardingPage = async () => {
	await page.goto(
		`${ WP_ADMIN_DASHBOARD }admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup`,
		{
			waitUntil: 'networkidle0',
		}
	);
	await uiLoaded();
};

const goToNextOnboardingStep = async () => {
	await page.click( SUBMIT_STEP_BTN_SELECTOR );
};

describe( 'Merchant On-boarding', () => {
	let activeThemeSlug;

	beforeAll( async () => {
		await merchant.login();
		// Get initial multi-currency feature status.
		await merchantWCP.openWCPSettings();
		await page.waitForSelector( MULTI_CURRENCY_TOGGLE_SELECTOR );
		wasMulticurrencyEnabled = await page.evaluate( ( selector ) => {
			const checkbox = document.querySelector( selector );
			return checkbox ? checkbox.checked : false;
		}, MULTI_CURRENCY_TOGGLE_SELECTOR );
		await merchantWCP.activateMulticurrency();

		await goToThemesPage();

		// Get current theme slug.
		activeThemeSlug = await page.evaluate( () => {
			const theme = document.querySelector( '.theme.active' );
			return theme ? theme.getAttribute( 'data-slug' ) : '';
		} );
	} );

	afterAll( async () => {
		// Restore original theme.
		await activateTheme( activeThemeSlug );

		// Disable multi-currency if it was not initially enabled.
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.login();
			await merchantWCP.deactivateMulticurrency();
		}
		await merchant.logout();
	} );

	describe( 'Currency Selection and Management', () => {
		beforeAll( async () => {
			await merchantWCP.disableAllEnabledCurrencies();
		} );

		beforeEach( async () => {
			await goToOnboardingPage();
		} );

		it( 'Should disable the submit button when no currencies are selected', async () => {
			await takeScreenshot( 'merchant-on-boarding-multicurrency-screen' );
			await setCheckboxState(
				`${ ENABLED_CURRENCY_LIST_SELECTOR } .components-checkbox-control__input`,
				false
			);

			await page.waitForTimeout( 1000 );

			const button = await page.$( SUBMIT_STEP_BTN_SELECTOR );
			expect( button ).not.toBeNull();

			const isDisabled = await page.evaluate(
				( btn ) => btn.disabled,
				button
			);

			expect( isDisabled ).toBeTruthy();
		} );

		it( 'Should allow multiple currencies to be selectable', async () => {
			await page.waitForSelector(
				CURRENCY_NOT_IN_RECOMMENDED_LIST_SELECTOR,
				{
					timeout: 3000,
				}
			);

			// Ensure the checkbox within the list item is present and not disabled.
			const checkbox = await page.$(
				`${ CURRENCY_NOT_IN_RECOMMENDED_LIST_SELECTOR } input[type="checkbox"]`
			);
			expect( checkbox ).not.toBeNull();
			const isDisabled = await (
				await checkbox.getProperty( 'disabled' )
			 ).jsonValue();
			expect( isDisabled ).toBe( false );

			// Click the checkbox to select the currency and verify it's checked.
			await checkbox.click();

			const isChecked = await (
				await checkbox.getProperty( 'checked' )
			 ).jsonValue();
			expect( isChecked ).toBe( true );
		} );

		it( 'Should exclude already enabled currencies from the currency screen', async () => {
			await merchantWCP.addCurrency( 'GBP' );

			await goToOnboardingPage();

			await page.waitForSelector( ENABLED_CURRENCY_LIST_SELECTOR, {
				timeout: 3000,
			} );

			// Get the list of currencies as text
			const currencies = await page.$$eval(
				ENABLED_CURRENCY_LIST_SELECTOR,
				( items ) => items.map( ( item ) => item.textContent.trim() )
			);

			expect( currencies ).not.toContain( 'GBP' );

			await merchantWCP.removeCurrency( 'GBP' );
		} );

		it( 'Should display some suggested currencies at the beginning of the list', async () => {
			await page.waitForSelector( RECOMMENDED_CURRENCY_LIST_SELECTOR, {
				timeout: 3000,
			} );

			// Get the list of recommended currencies
			const recommendedCurrencies = await page.$$eval(
				RECOMMENDED_CURRENCY_LIST_SELECTOR,
				( items ) =>
					items.map( ( item ) => ( {
						code: item
							.querySelector( 'input' )
							.getAttribute( 'code' ),
						name: item
							.querySelector(
								'span.enabled-currency-checkbox__code'
							)
							.textContent.trim(),
					} ) )
			);

			expect( recommendedCurrencies.length ).toBeGreaterThan( 0 );
		} );

		it( 'Should ensure selected currencies are enabled after submitting the form', async () => {
			const testCurrencies = [ 'GBP', 'EUR', 'CAD', 'AUD' ];
			const addCurrenciesContentSelector =
				'.add-currencies-task__content';
			const currencyCheckboxSelector = `${ addCurrenciesContentSelector } li input[type="checkbox"]`;

			await page.waitForSelector( addCurrenciesContentSelector, {
				timeout: 3000,
			} );

			// Select the currencies
			for ( const currency of testCurrencies ) {
				await setCheckboxState(
					`${ currencyCheckboxSelector }[code="${ currency }"]`,
					true
				);
			}

			// Submit the form.
			await goToNextOnboardingStep();

			await merchantWCP.openMultiCurrency();

			// Ensure the currencies are enabled.
			for ( const currency of testCurrencies ) {
				const selector = `li.enabled-currency.${ currency.toLowerCase() }`;
				await page.waitForSelector( selector, { timeout: 10000 } );
				const element = await page.$( selector );

				expect( element ).not.toBeNull();
			}
		} );
	} );

	describe( 'Geolocation Features', () => {
		beforeAll( async () => {
			await merchantWCP.disableAllEnabledCurrencies();
		} );

		beforeEach( async () => {
			await goToOnboardingPage();
		} );

		it( 'Should offer currency switch by geolocation', async () => {
			await goToNextOnboardingStep();

			const checkbox = await page.$(
				GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR
			);

			// Check if exists and not disabled.
			expect( checkbox ).not.toBeNull();
			const isDisabled = await (
				await checkbox.getProperty( 'disabled' )
			 ).jsonValue();
			expect( isDisabled ).toBe( false );

			// Click the checkbox to select it.
			await page.click( GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR );

			// Check if the checkbox is selected.
			const isChecked = await (
				await checkbox.getProperty( 'checked' )
			 ).jsonValue();
			expect( isChecked ).toBe( true );
		} );

		it( 'Should preview currency switch by geolocation correctly with USD and GBP', async () => {
			page.setViewport( { width: 1280, height: 1280 } ); // To take a better screenshot of the iframe preview.

			await goToNextOnboardingStep();

			await takeScreenshot(
				'merchant-on-boarding-multicurrency-screen-2'
			);

			// Enable feature.
			await setCheckboxState(
				GEO_CURRENCY_SWITCH_CHECKBOX_SELECTOR,
				true
			);

			// Click preview button.
			await page.click( PREVIEW_STORE_BTN_SELECTOR );

			await page.waitForSelector( PREVIEW_STORE_IFRAME_SELECTOR, {
				timeout: 3000,
			} );

			const iframeElement = await page.$( PREVIEW_STORE_IFRAME_SELECTOR );
			const iframe = await iframeElement.contentFrame();

			await iframe.waitForSelector( '.woocommerce-store-notice', {
				timeout: 3000,
			} );

			await takeScreenshot(
				'merchant-on-boarding-multicurrency-geolocation-switcher-preview'
			);

			const noticeText = await iframe.$eval(
				'.woocommerce-store-notice',
				( element ) => element.innerText
			);
			expect( noticeText ).toContain(
				// eslint-disable-next-line max-len
				"We noticed you're visiting from United Kingdom (UK). We've updated our prices to Pound sterling for your shopping convenience."
			);
		} );
	} );

	describe( 'Currency Switcher Widget', () => {
		it( 'Should offer the currency switcher widget while Storefront theme is active', async () => {
			await activateTheme( 'storefront' );

			await goToOnboardingPage();
			await goToNextOnboardingStep();

			const checkbox = await page.$(
				STOREFRONT_SWITCH_CHECKBOX_SELECTOR
			);

			// Check if exists and not disabled.
			expect( checkbox ).not.toBeNull();
			const isDisabled = await (
				await checkbox.getProperty( 'disabled' )
			 ).jsonValue();
			expect( isDisabled ).toBe( false );

			// Click the checkbox to select it.
			await page.click( STOREFRONT_SWITCH_CHECKBOX_SELECTOR );

			// Check if the checkbox is selected.
			const isChecked = await (
				await checkbox.getProperty( 'checked' )
			 ).jsonValue();
			expect( isChecked ).toBe( true );
		} );

		it( 'Should not offer the currency switcher widget when an unsupported theme is active', async () => {
			await activateTheme( 'twentytwentyfour' );

			await goToOnboardingPage();
			await goToNextOnboardingStep();

			const checkbox = await page.$(
				STOREFRONT_SWITCH_CHECKBOX_SELECTOR
			);

			expect( checkbox ).toBeNull();

			await activateTheme( 'storefront' );
		} );
	} );
} );
