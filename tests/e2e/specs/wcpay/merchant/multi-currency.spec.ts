/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import { useMerchant } from '../../../utils/helpers';
import {
	activateMulticurrency,
	addMulticurrencyWidget,
	deactivateMulticurrency,
	disableAllEnabledCurrencies,
	disableEditorWelcomeGuide,
	removeMultiCurrencyWidgets,
	restoreCurrencies,
} from '../../../utils/merchant';
import * as navigation from '../../../utils/merchant-navigation';

test.describe( 'Multi-currency', { tag: '@critical' }, () => {
	let wasMulticurrencyEnabled: boolean;
	let page: Page;

	// Use the merchant user for this test suite.
	useMerchant();

	test.beforeAll( async ( { browser } ) => {
		page = await browser.newPage();
		wasMulticurrencyEnabled = await activateMulticurrency( page );

		await disableAllEnabledCurrencies( page );
	} );

	test.afterAll( async ( {}, { project } ) => {
		await restoreCurrencies( page );
		await removeMultiCurrencyWidgets( project.use.baseURL );
		if ( ! wasMulticurrencyEnabled ) {
			await deactivateMulticurrency( page );
		}
		await page.close();
	} );

	test( 'page load without any errors', async () => {
		await navigation.goToMultiCurrencySettings( page );
		await expect(
			page.getByRole( 'heading', { name: 'Enabled currencies' } )
		).toBeVisible();
		await expect( page.getByText( 'Default currency' ) ).toBeVisible();
		// TODO: fix flaky visual regression test.
		// await expect(
		// 	page.locator( '.multi-currency-settings' ).last()
		// ).toHaveScreenshot();
	} );

	test( 'add the currency switcher to the sidebar', async ( {}, {
		project,
	} ) => {
		await addMulticurrencyWidget( page, project.use.baseURL );
	} );

	test( 'can add the currency switcher to a post/page and verify on frontend', async () => {
		// Restore currencies so the switcher block has currencies to display.
		await restoreCurrencies( page );

		await navigation.goToNewPost( page );

		// Disable the editor Welcome Guide so its modal overlay doesn't intercept
		// clicks on the block inserter (see helper for the WP-nightly background).
		await disableEditorWelcomeGuide( page );

		// The block editor canvas is iframed on modern WP (6.3+) but rendered
		// inline on the older WP bundled with WC 7.7.0. `isVisible()` does not
		// auto-wait, so checking it immediately after load raced the iframe mount
		// and — deterministically on WP nightly — fell through to the inline path,
		// where no page-level "Add block" button exists, hanging the test until it
		// timed out. Wait for the canvas to mount before choosing a path.
		const editorCanvas = page.locator( '[name="editor-canvas"]' );
		const usesIframedCanvas = await editorCanvas
			.waitFor( { state: 'visible', timeout: 15000 } )
			.then( () => true )
			.catch( () => false );

		if ( usesIframedCanvas ) {
			const editor = editorCanvas.contentFrame();
			await editor.getByRole( 'button', { name: 'Add block' } ).click();
		} else {
			// Fallback for the inline (non-iframed) editor on WC 7.7.0.
			await page.getByRole( 'button', { name: 'Add block' } ).click();
		}

		await page
			.locator( 'input[placeholder="Search"]' )
			.pressSequentially( 'switcher', { delay: 20 } );
		await expect(
			page.getByRole( 'option', { name: 'Currency Switcher Block' } )
		).toBeVisible();

		// Insert the block.
		await page
			.getByRole( 'option', { name: 'Currency Switcher Block' } )
			.click();

		// Publish the post — click the top bar button to open the publish panel.
		await page
			.getByLabel( 'Editor top bar' )
			.getByRole( 'button', { name: 'Publish' } )
			.click();
		// Confirm publish in the panel.
		await page
			.getByLabel( 'Editor publish' )
			.getByRole( 'button', { name: 'Publish', exact: true } )
			.click();

		// Wait for the post-publish panel to confirm and show the post link.
		const viewPostLink = page
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
		await page.goto( postUrl, { waitUntil: 'load' } );

		// Verify the currency switcher block renders in the post content.
		await expect(
			page.locator( '.entry-content .currency-switcher-holder' )
		).toBeVisible();
	} );
} );
