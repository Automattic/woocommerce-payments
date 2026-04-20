/**
 * External dependencies
 */
import { test, expect } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import {
	activateMulticurrency,
	addMulticurrencyWidget,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	removeMultiCurrencyWidgets,
	restoreCurrencies,
	goToMultiCurrencySettings,
	goToNewPost,
} from '../../../utils/merchant';

test.describe( 'Multi-currency', { tag: [ '@merchant', '@critical' ] }, () => {
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { adminPage } ) => {
		wasMulticurrencyEnabled = await activateMulticurrency( adminPage );
		await disableAllEnabledCurrencies( adminPage );
	} );

	test.afterAll( async ( { adminPage } ) => {
		await restoreCurrencies( adminPage );
		await removeMultiCurrencyWidgets();
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( adminPage );
		}
	} );

	test( 'page load without any errors', async ( { adminPage } ) => {
		await goToMultiCurrencySettings( adminPage );
		await expect(
			adminPage.getByRole( 'heading', { name: 'Enabled currencies' } )
		).toBeVisible();
		await expect( adminPage.getByText( 'Default currency' ) ).toBeVisible();
		// TODO: fix flaky visual regression test.
		// await expect(
		// 	adminPage.locator( '.multi-currency-settings' ).last()
		// ).toHaveScreenshot();
	} );

	test( 'add the currency switcher to the sidebar', async ( {
		adminPage,
	} ) => {
		await addMulticurrencyWidget( adminPage );
	} );

	test( 'can add the currency switcher to a post/page and verify on frontend', async ( {
		adminPage,
	} ) => {
		// Restore currencies so the switcher block has currencies to display.
		await restoreCurrencies( adminPage );

		await goToNewPost( adminPage );

		if (
			await adminPage.getByRole( 'button', { name: 'Close' } ).isVisible()
		) {
			await adminPage.getByRole( 'button', { name: 'Close' } ).click();
		}

		if ( await adminPage.locator( '[name="editor-canvas"]' ).isVisible() ) {
			await expect(
				adminPage.locator( '[name="editor-canvas"]' )
			).toBeAttached();
			const editor = adminPage
				.locator( '[name="editor-canvas"]' )
				.contentFrame();
			await editor.getByRole( 'button', { name: 'Add block' } ).click();
		} else {
			// Fallback for WC 7.7.0.
			await adminPage
				.getByRole( 'button', { name: 'Add block' } )
				.click();
		}

		await adminPage
			.locator( 'input[placeholder="Search"]' )
			.pressSequentially( 'switcher', { delay: 20 } );
		await expect(
			adminPage.getByRole( 'option', {
				name: 'Currency Switcher Block',
			} )
		).toBeVisible();

		// Insert the block.
		await adminPage
			.getByRole( 'option', { name: 'Currency Switcher Block' } )
			.click();

		// Publish the post — click the top bar button to open the publish panel.
		await adminPage
			.getByLabel( 'Editor top bar' )
			.getByRole( 'button', { name: 'Publish' } )
			.click();
		// Confirm publish in the panel.
		await adminPage
			.getByLabel( 'Editor publish' )
			.getByRole( 'button', { name: 'Publish', exact: true } )
			.click();

		// Wait for the post-publish panel to confirm and show the post link.
		const viewPostLink = adminPage
			.getByLabel( 'Editor publish' )
			.getByRole( 'link', { name: 'View Post' } );
		await expect( viewPostLink ).toBeVisible( { timeout: 10000 } );

		// The "View Post" link opens in a new tab — navigate directly instead.
		const postUrl = await viewPostLink.getAttribute( 'href' );
		if ( ! postUrl ) {
			throw new Error(
				'View Post link does not have an href attribute.'
			);
		}
		await adminPage.goto( postUrl, { waitUntil: 'load' } );

		// Verify the currency switcher block renders in the post content.
		await expect(
			adminPage.locator( '.entry-content .currency-switcher-holder' )
		).toBeVisible();
	} );
} );
