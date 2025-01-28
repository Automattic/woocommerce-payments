/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { getMerchant, getShopper } from '../../utils/helpers';
import {
	activateMulticurrency,
	addMulticurrencyWidget,
	deactivateMulticurrency,
	removeMultiCurrencyWidgets,
} from '../../utils/merchant';
import * as navigation from '../../utils/shopper-navigation';

test.describe( 'Multi-currency widget setup', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;
	// Values to test against. Defining nonsense values to ensure they are applied correctly.
	const settings = {
		borderRadius: '15',
		fontSize: '40',
		lineHeight: '2.3',
		textColor: 'rgb(155, 81, 224)',
		borderColor: 'rgb(252, 185, 0)',
	};

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await activateMulticurrency( merchantPage );

		await addMulticurrencyWidget( merchantPage, true );
	} );

	test.afterAll( async ( {}, { project } ) => {
		await removeMultiCurrencyWidgets( project.use.baseURL );

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( merchantPage );
		}

		await merchantPage.close();
	} );

	test( 'displays enabled currencies correctly in the admin', async () => {
		await expect(
			merchantPage
				.locator( 'select[name="currency"]' )
				.getByRole( 'option' )
		).toHaveCount( 3 );
		await expect(
			merchantPage
				.locator( 'select[name="currency"]' )
				.getByRole( 'option', { name: 'USD' } )
		).toBeAttached();
		await expect(
			merchantPage
				.locator( 'select[name="currency"]' )
				.getByRole( 'option', { name: 'EUR' } )
		).toBeAttached();
		await expect(
			merchantPage
				.locator( 'select[name="currency"]' )
				.getByRole( 'option', { name: 'GBP' } )
		).toBeAttached();
	} );

	test( 'can update widget properties', async () => {
		await test.step( 'opens widget settings', async () => {
			await merchantPage
				.getByRole( 'button', { name: 'Settings' } )
				.click();
			await merchantPage
				.locator( '[data-title="Currency Switcher Block"]' )
				.click();
		} );

		await test.step( 'checks display flags', async () => {
			await merchantPage
				.getByRole( 'checkbox', { name: 'Display flags' } )
				.check();
			await expect(
				await merchantPage
					.getByRole( 'checkbox', { name: 'Display flags' } )
					.isChecked()
			).toBeTruthy();
		} );

		await test.step( 'checks display currency symbols', async () => {
			await merchantPage
				.getByRole( 'checkbox', { name: 'Display currency symbols' } )
				.check();
			await expect(
				await merchantPage
					.getByRole( 'checkbox', {
						name: 'Display currency symbols',
					} )
					.isChecked()
			).toBeTruthy();
		} );

		await test.step( 'checks border', async () => {
			await merchantPage
				.getByRole( 'checkbox', { name: 'Border' } )
				.check();
			await expect(
				await merchantPage
					.getByRole( 'checkbox', { name: 'Border' } )
					.isChecked()
			).toBeTruthy();
		} );

		await test.step( 'updates border radius', async () => {
			await merchantPage
				.getByRole( 'spinbutton', { name: 'Border radius' } )
				.fill( settings.borderRadius );
		} );

		await test.step( 'updates font size', async () => {
			await merchantPage
				.getByRole( 'spinbutton', { name: 'Size' } )
				.fill( settings.fontSize );
		} );

		await test.step( 'updates line height', async () => {
			await merchantPage
				.getByRole( 'spinbutton', { name: 'Line height' } )
				.fill( settings.lineHeight );
		} );

		await test.step( 'updates text color', async () => {
			await merchantPage
				.locator( 'fieldset', { hasText: 'Text' } )
				.getByRole( 'listbox', { name: 'Custom color picker' } )
				.getByRole( 'option', { name: 'Vivid purple' } )
				.click();
		} );

		await test.step( 'updates border color', async () => {
			await merchantPage
				.locator( 'fieldset', { hasText: 'Border' } )
				.getByRole( 'listbox', { name: 'Custom color picker' } )
				.getByRole( 'option', { name: 'Luminous vivid amber' } )
				.click();
		} );

		await test.step( 'saves changes', async () => {
			await expect(
				merchantPage.getByRole( 'button', { name: 'Update' } )
			).toBeEnabled();
			await merchantPage
				.getByRole( 'button', { name: 'Update' } )
				.click();
			await expect(
				merchantPage.getByLabel( 'Dismiss this notice' )
			).toBeVisible( {
				timeout: 10000,
			} );
		} );
	} );

	test( 'displays enabled currencies correctly in the frontend', async () => {
		await navigation.goToShop( shopperPage );

		await expect(
			await shopperPage.locator( '.currency-switcher-holder' )
		).toBeVisible();
		await expect(
			shopperPage
				.locator( '.currency-switcher-holder' )
				.getByRole( 'option' )
		).toHaveCount( 3 );
		await expect(
			shopperPage
				.locator( '.currency-switcher-holder' )
				.getByRole( 'option', { name: 'USD' } )
		).toBeAttached();
		await expect(
			shopperPage
				.locator( '.currency-switcher-holder' )
				.getByRole( 'option', { name: 'EUR' } )
		).toBeAttached();
		await expect(
			shopperPage
				.locator( '.currency-switcher-holder' )
				.getByRole( 'option', { name: 'GBP' } )
		).toBeAttached();
	} );

	test( 'widget settings are applied in the frontend', async () => {
		await navigation.goToShop( shopperPage );

		// Asserts flags are displayed.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toContainText( '🇺🇸' );
		// Asserts currency symbols are displayed.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toContainText( '$' );
		// Asserts border is set.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toHaveCSS( 'border-top-width', '1px' );
		// Asserts border radius is set.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toHaveCSS( 'border-top-left-radius', `${ settings.borderRadius }px` );
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toHaveCSS( 'font-size', `${ settings.fontSize }px` );
		await expect(
			await shopperPage.locator( '.currency-switcher-holder' )
		).toHaveAttribute( 'style', `line-height: ${ settings.lineHeight }; ` ); // Trailing space is expected.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toHaveCSS( 'color', settings.textColor );
		// Asserts border color is set.
		await expect(
			await shopperPage.locator( '.currency-switcher-holder select' )
		).toHaveCSS( 'border-top-color', settings.borderColor );
	} );
} );
