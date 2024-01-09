/**
 * External dependencies
 */
const { merchant, WP_ADMIN_DASHBOARD } = require( '@woocommerce/e2e-utils' );
/**
 * Internal dependencies
 */
import { merchantWCP, uiLoaded } from '../../../utils';

let wasMulticurrencyEnabled;

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

	describe.skip( 'Currency Selection and Management', () => {
		beforeAll( async () => {
			await merchantWCP.disableAllEnabledCurrencies();
		} );

		beforeEach( async () => {
			await goToOnboardingPage();
		} );

		it.skip( 'Should disable the submit button when no currencies are selected', async () => {
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
		} );

		it.skip( 'Should allow multiple currencies to be selectable', async () => {
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

		it.skip( 'Should exclude already enabled currencies from the currency screen', async () => {
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

		it.skip( 'Should display some suggested currencies at the beginning of the list', async () => {
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
				await page.click(
					`${ currencyCheckboxSelector }[code="${ currency }"]`
				);
			}

			// Submit the form.
			const submitButton = await page.$(
				'.add-currencies-task.is-active .task-collapsible-body.is-active > button.is-primary'
			);
			await submitButton.click();

			await merchantWCP.openMultiCurrency();

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
			await checkbox.click();

			// Check if the checkbox is selected.
			const isChecked = await (
				await checkbox.getProperty( 'checked' )
			 ).jsonValue();
			expect( isChecked ).toBe( true );
		} );

		it( 'Should preview currency switch by geolocation correctly with USD and GBP', async () => {
			// Implement test
		} );
	} );

	describe.skip( 'Currency Switcher Widget', () => {
		it( 'Should offer the currency switcher widget while Storefront theme is active', async () => {
			// Implement test
		} );

		it( 'Should not offer the currency switcher widget when an unsupported theme is active', async () => {
			// Implement test
		} );
	} );
} );
