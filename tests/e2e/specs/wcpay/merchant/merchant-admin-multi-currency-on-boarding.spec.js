/**
 * External dependencies
 */
const { merchant, WP_ADMIN_DASHBOARD } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { merchantWCP, takeScreenshot, uiLoaded } from '../../../utils';

let wasMulticurrencyEnabled;

const goToThemesPage = async () => {
	await page.goto( `${ WP_ADMIN_DASHBOARD }themes.php`, {
		waitUntil: 'networkidle0',
	} );
};

const activateTheme = async ( themeSlug ) => {
	await goToThemesPage();

	const themeSelector = `.theme[data-slug="${ themeSlug }"]`;
	const activateButtonSelector = `${ themeSelector } .button.activate`;

	// Check if the theme is already active.
	const isActive = await page.evaluate( ( selector ) => {
		const themeElement = document.querySelector( selector );
		return themeElement && themeElement.classList.contains( 'active' );
	}, themeSelector );

	// Activate the theme if it's not already active.
	if ( ! isActive ) {
		await page.click( activateButtonSelector );
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
	const continueBtnSelector =
		'.add-currencies-task.is-active .task-collapsible-body.is-active > button.is-primary';

	await page.click( continueBtnSelector );
};

describe( 'Merchant On-boarding', () => {
	let activeThemeSlug;

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

		await goToThemesPage();

		// Get current theme slug.
		activeThemeSlug = await page.evaluate( () => {
			const theme = document.querySelector( '.theme.active-theme' );
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
			const checkboxes = await page.$$(
				'.enabled-currency-checkbox .components-checkbox-control__input'
			);

			for ( const checkbox of checkboxes ) {
				const isChecked = await (
					await checkbox.getProperty( 'checked' )
				 ).jsonValue();
				if ( isChecked ) {
					// Click the checkbox to uncheck it if it's checked
					await checkbox.click();
				}
			}

			await page.waitFor( 1000 );

			const button = await page.$(
				'.add-currencies-task.is-active .task-collapsible-body.is-active > button.is-primary'
			);

			expect( button ).not.toBeNull();

			const isDisabled = await page.evaluate(
				( btn ) => btn.disabled,
				button
			);

			expect( isDisabled ).toBeTruthy();

			await takeScreenshot(
				'merchant-admin-multi-currency-on-boarding-disabled-submit-button'
			);
		} );

		it( 'Should allow multiple currencies to be selectable', async () => {
			const listItemSelector =
				'li.enabled-currency-checkbox:not([data-testid="recommended-currency"])';
			const checkboxSelector = 'input[type="checkbox"]';

			await page.waitForSelector( listItemSelector, {
				timeout: 3000,
			} );

			// Ensure the checkbox within the list item is present and not disabled.
			const checkbox = await page.$(
				`${ listItemSelector } ${ checkboxSelector }`
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
			const currencySelector = 'li.enabled-currency-checkbox';

			await page.waitForSelector( currencySelector, {
				timeout: 3000,
			} );

			// Get the list of currencies as text
			const currencies = await page.$$eval( currencySelector, ( items ) =>
				items.map( ( item ) => item.textContent.trim() )
			);

			expect( currencies ).not.toContain( 'GBP' );

			await merchantWCP.removeCurrency( 'GBP' );
		} );

		it( 'Should display some suggested currencies at the beginning of the list', async () => {
			const recommendedCurrencySelector =
				'li[data-testid="recommended-currency"]';

			await page.waitForSelector( recommendedCurrencySelector, {
				timeout: 3000,
			} );

			// Get the list of recommended currencies
			const recommendedCurrencies = await page.$$eval(
				recommendedCurrencySelector,
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

			await takeScreenshot(
				'merchant-admin-multi-currency-on-boarding-recommended-currencies'
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
				const checkboxSelector = `${ currencyCheckboxSelector }[code="${ currency }"]`;
				const isChecked = await page.evaluate( ( selector ) => {
					const checkbox = document.querySelector( selector );
					return checkbox && checkbox.checked;
				}, checkboxSelector );

				if ( ! isChecked ) {
					await page.click( checkboxSelector );
				}
			}

			// Submit the form.
			const submitButton = await page.$(
				'.add-currencies-task.is-active .task-collapsible-body.is-active > button.is-primary'
			);
			await submitButton.click();

			await merchantWCP.openMultiCurrency();

			await takeScreenshot(
				'merchant-admin-multi-currency-on-boarding-enabled-currencies'
			);

			// Ensure the currencies are enabled.
			for ( const currency of testCurrencies ) {
				const selector = `li.enabled-currency.${ currency.toLowerCase() }`;
				await page.waitForSelector( selector );
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

			const geoCurrencySwitchCheckboxSelector =
				'input[data-testid="enable_auto_currency"]';
			const checkbox = await page.$( geoCurrencySwitchCheckboxSelector );

			// Check if exists and not disabled.
			expect( checkbox ).not.toBeNull();
			const isDisabled = await (
				await checkbox.getProperty( 'disabled' )
			 ).jsonValue();
			expect( isDisabled ).toBe( false );

			// Click the checkbox to select it.
			await page.click( geoCurrencySwitchCheckboxSelector );

			// Check if the checkbox is selected.
			const isChecked = await (
				await checkbox.getProperty( 'checked' )
			 ).jsonValue();
			expect( isChecked ).toBe( true );
		} );

		it( 'Should preview currency switch by geolocation correctly with USD and GBP', async () => {
			await goToNextOnboardingStep();

			const geoCurrencySwitchCheckboxSelector =
				'input[data-testid="enable_auto_currency"]';
			const previewBtnSelector = '.multi-currency-setup-preview-button';
			const previewModalSelector =
				'.multi-currency-store-settings-preview-modal';
			const iframeSelector =
				'.multi-currency-store-settings-preview-iframe';

			// Enable feature.
			await page.click( geoCurrencySwitchCheckboxSelector );

			// Click preview button.
			await page.click( previewBtnSelector );

			await page.waitForSelector( previewModalSelector, {
				timeout: 3000,
			} );

			await page.waitForSelector( iframeSelector, {
				timeout: 3000,
			} );

			const iframeElement = await page.$( iframeSelector );
			const iframe = await iframeElement.contentFrame();

			await iframe.waitForSelector( '.woocommerce-Price-currencySymbol', {
				timeout: 5000,
			} );

			// Assert that all occurrences of '.woocommerce-Price-currencySymbol' have the sterling pound symbol
			const currencySymbols = await iframe.$$eval(
				'.woocommerce-Price-currencySymbol',
				( elements ) =>
					elements.map( ( element ) => element.textContent )
			);
			currencySymbols.forEach( ( symbol ) => {
				expect( symbol ).toBe( '£' );
			} );

			await iframe.waitForSelector( '.woocommerce-store-notice', {
				timeout: 3000,
			} );

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

			const storefrontSwitchCheckboxSelector =
				'input[data-testid="enable_storefront_switcher"]';
			const checkbox = await page.$( storefrontSwitchCheckboxSelector );

			// Check if exists and not disabled.
			expect( checkbox ).not.toBeNull();
			const isDisabled = await (
				await checkbox.getProperty( 'disabled' )
			 ).jsonValue();
			expect( isDisabled ).toBe( false );

			// Click the checkbox to select it.
			await page.click( storefrontSwitchCheckboxSelector );

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

			const storefrontSwitchCheckboxSelector =
				'input[data-testid="enable_storefront_switcher"]';
			const checkbox = await page.$( storefrontSwitchCheckboxSelector );

			expect( checkbox ).toBeNull();
		} );
	} );
} );
