/**
 * Internal dependencies
 */
import { test, expect } from '../../../fixtures/auth';
import {
	activateMulticurrency,
	addMulticurrencyWidget,
	deactivateMulticurrency,
	removeMultiCurrencyWidgets,
	restoreCurrencies,
} from '../../../utils/merchant';
import { goToShop } from '../../../utils/shopper-navigation';

test.describe( 'Multi-currency widget setup', { tag: '@merchant' }, () => {
	let wasMulticurrencyEnabled: boolean;
	// Values to test against. Defining nonsense values to ensure they are applied correctly.
	const settings = {
		borderRadius: '15',
		fontSize: '40',
		lineHeight: '2.3',
		textColor: 'rgb(155, 81, 224)',
		borderColor: 'rgb(252, 185, 0)',
	};

	test.beforeAll( async ( { adminPage } ) => {
		wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
		await restoreCurrencies( adminPage );

		await addMulticurrencyWidget( adminPage, true );
	} );

	test.afterAll( async ( { adminPage } ) => {
		await removeMultiCurrencyWidgets();

		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( adminPage );
		}
	} );

	test(
		'displays enabled currencies correctly in the admin',
		{ tag: '@critical' },
		async ( { adminPage } ) => {
			// Navigate to widgets page where currency selector should be visible
			await adminPage.goto( '/wp-admin/widgets.php', {
				waitUntil: 'load',
			} );

			// Wait for the widget to load
			await adminPage.waitForTimeout( 2000 );

			await expect(
				adminPage
					.locator(
						'[data-title="Currency Switcher Block"] select[name="currency"]'
					)
					.getByRole( 'option' )
			).toHaveCount( 3 );
			await expect(
				adminPage
					.locator(
						'[data-title="Currency Switcher Block"] select[name="currency"]'
					)
					.getByRole( 'option', { name: 'USD' } )
			).toBeAttached();
			await expect(
				adminPage
					.locator(
						'[data-title="Currency Switcher Block"] select[name="currency"]'
					)
					.getByRole( 'option', { name: 'EUR' } )
			).toBeAttached();
			await expect(
				adminPage
					.locator(
						'[data-title="Currency Switcher Block"] select[name="currency"]'
					)
					.getByRole( 'option', { name: 'GBP' } )
			).toBeAttached();
		}
	);

	test(
		'can update widget properties',
		{ tag: '@critical' },
		async ( { adminPage } ) => {
			await test.step( 'opens widget settings', async () => {
				await adminPage.goto( '/wp-admin/widgets.php', {
					waitUntil: 'load',
				} );

				// Ensure settings panel is open (QIT equivalent of ensureBlockSettingsPanelIsOpen)
				const settingsButton = adminPage.locator(
					'.interface-pinned-items > button[aria-label="Settings"]'
				);
				const isSettingsButtonPressed = await settingsButton.evaluate(
					( node ) => node.getAttribute( 'aria-pressed' ) === 'true'
				);

				if ( ! isSettingsButtonPressed ) {
					await settingsButton.click();
				}

				await adminPage
					.locator( '[data-title="Currency Switcher Block"]' )
					.click();
			} );

			await test.step( 'checks display flags', async () => {
				await adminPage
					.getByRole( 'checkbox', { name: 'Display flags' } )
					.check();
				expect(
					await adminPage
						.getByRole( 'checkbox', { name: 'Display flags' } )
						.isChecked()
				).toBeTruthy();
			} );

			await test.step( 'checks display currency symbols', async () => {
				await adminPage
					.getByRole( 'checkbox', {
						name: 'Display currency symbols',
					} )
					.check();
				expect(
					await adminPage
						.getByRole( 'checkbox', {
							name: 'Display currency symbols',
						} )
						.isChecked()
				).toBeTruthy();
			} );

			await test.step( 'checks border', async () => {
				await adminPage
					.getByRole( 'checkbox', { name: 'Border' } )
					.check();
				expect(
					await adminPage
						.getByRole( 'checkbox', { name: 'Border' } )
						.isChecked()
				).toBeTruthy();
			} );

			await test.step( 'updates border radius', async () => {
				await adminPage
					.getByRole( 'spinbutton', { name: 'Border radius' } )
					.fill( settings.borderRadius );
			} );

			await test.step( 'updates font size', async () => {
				await adminPage
					.getByRole( 'spinbutton', { name: 'Size' } )
					.fill( settings.fontSize );
			} );

			await test.step( 'updates line height', async () => {
				await adminPage
					.getByRole( 'spinbutton', { name: 'Line height' } )
					.fill( settings.lineHeight );
			} );

			await test.step( 'updates text color', async () => {
				await adminPage
					.locator( 'fieldset', { hasText: 'Text' } )
					.getByRole( 'listbox', { name: 'Custom color picker' } )
					.getByRole( 'option', { name: 'Vivid purple' } )
					.click();
			} );

			await test.step( 'updates border color', async () => {
				await adminPage
					.locator( 'fieldset', { hasText: 'Border' } )
					.getByRole( 'listbox', { name: 'Custom color picker' } )
					.getByRole( 'option', { name: 'Luminous vivid amber' } )
					.click();
			} );

			await test.step( 'saves changes', async () => {
				await expect(
					adminPage.getByRole( 'button', { name: 'Update' } )
				).toBeEnabled();
				await adminPage
					.getByRole( 'button', { name: 'Update' } )
					.click();
				await expect(
					adminPage.getByLabel( 'Dismiss this notice' )
				).toBeVisible( {
					timeout: 10000,
				} );
			} );
		}
	);

	test(
		'displays enabled currencies correctly in the frontend',
		{ tag: '@critical' },
		async ( { customerPage } ) => {
			await goToShop( customerPage );

			await expect(
				customerPage.locator( '.currency-switcher-holder' )
			).toBeVisible();
			await expect(
				customerPage
					.locator( '.currency-switcher-holder' )
					.getByRole( 'option' )
			).toHaveCount( 3 );
			await expect(
				customerPage
					.locator( '.currency-switcher-holder' )
					.getByRole( 'option', { name: 'USD' } )
			).toBeAttached();
			await expect(
				customerPage
					.locator( '.currency-switcher-holder' )
					.getByRole( 'option', { name: 'EUR' } )
			).toBeAttached();
			await expect(
				customerPage
					.locator( '.currency-switcher-holder' )
					.getByRole( 'option', { name: 'GBP' } )
			).toBeAttached();
		}
	);

	test(
		'widget settings are applied in the frontend',
		{ tag: '@critical' },
		async ( { customerPage } ) => {
			await goToShop( customerPage );

			// Asserts flags are displayed.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toContainText( '🇺🇸' );
			// Asserts currency symbols are displayed.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toContainText( '$' );
			// Asserts border is set.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toHaveCSS( 'border-top-width', '1px' );
			// Asserts border radius is set.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toHaveCSS(
				'border-top-left-radius',
				`${ settings.borderRadius }px`
			);
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toHaveCSS( 'font-size', `${ settings.fontSize }px` );
			await expect(
				customerPage.locator( '.currency-switcher-holder' )
			).toHaveAttribute(
				'style',
				`line-height: ${ settings.lineHeight }; `
			); // Trailing space is expected.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toHaveCSS( 'color', settings.textColor );
			// Asserts border color is set.
			await expect(
				customerPage.locator( '.currency-switcher-holder select' )
			).toHaveCSS( 'border-top-color', settings.borderColor );
		}
	);
} );
