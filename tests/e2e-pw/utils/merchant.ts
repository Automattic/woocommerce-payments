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

export const activateMulticurrency = async ( page: Page ) => {
	await navigation.goToWooPaymentsSettings( page );

	const checkboxTestId = 'multi-currency-toggle';
	const wasInitiallyEnabled = await page
		.getByTestId( checkboxTestId )
		.isChecked();

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

export const getActiveThemeSlug = async ( page: Page ): Promise< string > => {
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
		await page.waitForURL( `**/wp-admin/themes.php?activated=true` );
	}
};

export const disableAllEnabledCurrencies = async ( page: Page ) => {
	await navigation.goToMultiCurrencySettings( page );
	await expect(
		await page.locator( '.enabled-currencies-list li' )
	).toBeVisible();

	const deleteButtons = await page
		.locator( '.enabled-currency .enabled-currency__action.delete' )
		.all();

	for ( const button of deleteButtons ) {
		await button.click();
		await expect( page.getByLabel( 'Dismiss this notice' ) ).toBeVisible( {
			timeout: 10000,
		} );
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
