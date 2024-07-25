/**
 * External dependencies
 */
import { Page, expect } from 'playwright/test';
import * as navigation from './merchant-navigation';

export const saveWooPaymentsSettings = async ( page: Page ) => {
	await page.getByRole( 'button', { name: 'Save changes' } ).click();
	await expect(
		page.getByRole( 'button', { name: 'Settings saved.' } )
	).toBeVisible( { timeout: 10000 } );
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
		// For some reason the alert "Widgets saved." doesn't show up in the testing.
		// So we refresh the page to make sure it's really there.
		await page.waitForResponse( '**/wp-json/wp/v2/sidebars**' );
		await page.reload( { waitUntil: 'load' } );
		// Wait for all widgets to load. This is important to prevent flakiness.
		await expect( page.locator( '.components-spinner' ) ).toHaveCount( 0 );
		await expect(
			page.getByRole( 'heading', { name: 'Currency Switcher Widget' } )
		).toBeVisible();
	}
};
