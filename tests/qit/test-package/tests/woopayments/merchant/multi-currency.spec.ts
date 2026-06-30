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

		// Modern WP shows the editor Welcome Guide on a fresh post, and its modal
		// overlay intercepts clicks on the block inserter. Disable it (and
		// fullscreen mode) deterministically via the preferences store: racing a
		// "Close" button with isVisible() let the guide slip through on WP nightly
		// and block the insert. Guarded so it no-ops where core/preferences is
		// unavailable.
		await adminPage.waitForFunction( () => ( window as any ).wp?.data );
		await adminPage.evaluate( async () => {
			const prefs = ( window as any ).wp?.data?.dispatch?.(
				'core/preferences'
			);
			if ( prefs?.set ) {
				await prefs.set( 'core/edit-post', 'welcomeGuide', false );
				await prefs.set( 'core/edit-post', 'fullscreenMode', false );
			}
		} );

		// The block editor canvas is iframed on modern WP (6.3+) but rendered
		// inline on the older WP bundled with WC 7.7.0. `isVisible()` does not
		// auto-wait, so checking it immediately after load raced the iframe mount
		// and — deterministically on WP nightly — fell through to the inline path,
		// where no page-level "Add block" button exists, hanging the test until it
		// timed out. Wait for the canvas to mount before choosing a path.
		const editorCanvas = adminPage.locator( '[name="editor-canvas"]' );
		const usesIframedCanvas = await editorCanvas
			.waitFor( { state: 'visible', timeout: 15000 } )
			.then( () => true )
			.catch( () => false );

		if ( usesIframedCanvas ) {
			const editor = editorCanvas.contentFrame();
			await editor.getByRole( 'button', { name: 'Add block' } ).click();
		} else {
			// Fallback for the inline (non-iframed) editor on WC 7.7.0.
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
