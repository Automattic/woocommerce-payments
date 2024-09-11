/**
 * External dependencies
 */
import { Page, expect } from 'playwright/test';
import * as navigation from './merchant-navigation';

/**
 * Checks if the data has loaded on the page.
 * This check only applies to WooPayments settings pages.
 *
 * @param {Page} page The page object.
 */
export const dataHasLoaded = async ( page: Page ) => {
	await expect( page.locator( '.is-loadable-placeholder' ) ).toHaveCount( 0 );
};

export const saveWooPaymentsSettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect( page.getByLabel( 'Dismiss this notice' ) ).toBeVisible( {
		timeout: 10000,
	} );
};

export const isMulticurrencyEnabled = async ( page: Page ) => {
	await navigation.goToWooPaymentsSettings( page );

	const checkboxTestId = 'multi-currency-toggle';
	const isEnabled = await page.getByTestId( checkboxTestId ).isChecked();

	return isEnabled;
};

export const activateMulticurrency = async ( page: Page ) => {
	await navigation.goToWooPaymentsSettings( page );

	const checkboxTestId = 'multi-currency-toggle';
	const wasInitiallyEnabled = await isMulticurrencyEnabled( page );

	if ( ! wasInitiallyEnabled ) {
		await page.getByTestId( checkboxTestId ).check();
		await saveWooPaymentsSettings( page );
	}
	return wasInitiallyEnabled;
};

export const deactivateMulticurrency = async ( page: Page ) => {
	await navigation.goToWooPaymentsSettings( page );
	await page.getByTestId( 'multi-currency-toggle' ).uncheck();
	await saveWooPaymentsSettings( page );
};

export const addMulticurrencyWidget = async ( page: Page ) => {
	await navigation.goToWidgets( page );
	// Wait for all widgets to load. This is important to prevent flakiness.
	await expect( page.locator( '.components-spinner' ) ).toHaveCount( 0 );

	if ( await page.getByRole( 'button', { name: 'Close' } ).isVisible() ) {
		await page.getByRole( 'button', { name: 'Close' } ).click();
	}

	const isWidgetAdded = await page
		.getByRole( 'heading', { name: 'Currency Switcher Widget' } )
		.isVisible();

	if ( ! isWidgetAdded ) {
		await page.getByRole( 'button', { name: 'Add block' } ).click();
		await page
			.locator( 'input[placeholder="Search"]' )
			.pressSequentially( 'switcher', { delay: 20 } );
		await expect(
			page.locator( 'button.components-button[role="option"]' ).first()
		).toBeVisible( { timeout: 5000 } );
		await page
			.locator( 'button.components-button[role="option"]' )
			.first()
			.click();
		await page.waitForTimeout( 2000 );
		await expect(
			page.getByRole( 'button', { name: 'Update' } )
		).toBeEnabled();
		await page.getByRole( 'button', { name: 'Update' } ).click();
		await expect( page.getByLabel( 'Dismiss this notice' ) ).toBeVisible( {
			timeout: 10000,
		} );
	}
};

export const getActiveThemeSlug = async ( page: Page ) => {
	await navigation.goToThemes( page );

	const activeTheme = await page.locator( '.theme.active' );

	return ( await activeTheme.getAttribute( 'data-slug' ) ) ?? '';
};

export const activateTheme = async ( page: Page, slug: string ) => {
	await navigation.goToThemes( page );

	const isThemeActive = ( await getActiveThemeSlug( page ) ) === slug;

	if ( ! isThemeActive ) {
		await page
			.locator( `.theme[data-slug="${ slug }"] .button.activate` )
			.click();
		await expect(
			await page.locator( '.notice.updated' ).innerText()
		).toContain( 'New theme activated.' );
	}
};

export const disableAllEnabledCurrencies = async ( page: Page ) => {
	await navigation.goToMultiCurrencySettings( page );
	await expect(
		await page.locator( '.enabled-currencies-list li' ).first()
	).toBeVisible();

	const deleteButtons = await page
		.locator( '.enabled-currency .enabled-currency__action.delete' )
		.all();

	if ( deleteButtons.length === 0 ) {
		return;
	}

	for ( let i = 0; i < deleteButtons.length; i++ ) {
		await page
			.locator( '.enabled-currency .enabled-currency__action.delete' )
			.first()
			.click();

		const snackbar = await page.getByLabel( 'Dismiss this notice' );

		await expect( snackbar ).toBeVisible( { timeout: 10000 } );
		await snackbar.click();
		await expect( snackbar ).toBeHidden( { timeout: 10000 } );
	}
};

export const addCurrency = async ( page: Page, currencyCode: string ) => {
	// Default currency.
	if ( currencyCode === 'USD' ) {
		return;
	}

	await navigation.goToMultiCurrencySettings( page );
	await page.getByTestId( 'enabled-currencies-add-button' ).click();

	const checkbox = await page.locator(
		`input[type="checkbox"][code="${ currencyCode }"]`
	);

	if ( ! ( await checkbox.isChecked() ) ) {
		await checkbox.check();
	}

	await page.getByRole( 'button', { name: 'Update selected' } ).click();
	await expect( page.getByLabel( 'Dismiss this notice' ) ).toBeVisible( {
		timeout: 10000,
	} );
	await expect(
		page.locator( `li.enabled-currency.${ currencyCode.toLowerCase() }` )
	).toBeVisible();
};

export const removeCurrency = async ( page: Page, currencyCode: string ) => {
	await navigation.goToMultiCurrencySettings( page );
	await page
		.locator(
			`li.enabled-currency.${ currencyCode.toLowerCase() } .enabled-currency__action.delete`
		)
		.click();
	await expect( page.getByLabel( 'Dismiss this notice' ) ).toBeVisible( {
		timeout: 10000,
	} );
	await expect(
		page.locator( `li.enabled-currency.${ currencyCode.toLowerCase() }` )
	).toBeHidden();
};

export const editCurrency = async ( page: Page, currencyCode: string ) => {
	await navigation.goToMultiCurrencySettings( page );
	await page
		.locator(
			`.enabled-currency.${ currencyCode.toLowerCase() } .enabled-currency__action.edit`
		)
		.click();
	await dataHasLoaded( page );
};

export const setCurrencyRate = async (
	page: Page,
	currencyCode: string,
	rate: string
) => {
	await editCurrency( page, currencyCode );
	await page
		.locator( '#single-currency-settings__manual_rate_radio' )
		.click();
	await page.getByTestId( 'manual_rate_input' ).fill( rate );
	await saveWooPaymentsSettings( page );
};

export const setCurrencyPriceRounding = async (
	page: Page,
	currencyCode: string,
	rounding: string
) => {
	await editCurrency( page, currencyCode );
	await page.getByTestId( 'price_rounding' ).selectOption( rounding );
	await saveWooPaymentsSettings( page );
};

export const setCurrencyCharmPricing = async (
	page: Page,
	currencyCode: string,
	charmPricing: string
) => {
	await editCurrency( page, currencyCode );
	await page.getByTestId( 'price_charm' ).selectOption( charmPricing );
	await saveWooPaymentsSettings( page );
};

export const enablePaymentMethods = async (
	page: Page,
	paymentMethods: string[]
) => {
	await navigation.goToWooPaymentsSettings( page );

	for ( const paymentMethodName of paymentMethods ) {
		await page.getByLabel( paymentMethodName ).check();
	}

	await saveWooPaymentsSettings( page );
};

export const disablePaymentMethods = async (
	page: Page,
	paymentMethods: string[]
) => {
	await navigation.goToWooPaymentsSettings( page );

	for ( const paymentMethodName of paymentMethods ) {
		const checkbox = await page.getByLabel( paymentMethodName );

		if ( await checkbox.isChecked() ) {
			await checkbox.click();
			await page.getByRole( 'button', { name: 'Remove' } ).click();
		}
	}

	await saveWooPaymentsSettings( page );
};
