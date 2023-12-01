/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, takeScreenshot, uiLoaded } from '../../../utils';

describe( 'Admin Multi-Currency', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openMultiCurrency();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Enabled currencies',
		} );
		await takeScreenshot( 'merchant-admin-multi-currency' );
	} );

	it( 'should be possible to add the currency switcher to the sidebar', async () => {
		await merchantWCP.addMulticurrencyWidget();
	} );

	it( 'should be possible to add the currency switcher to a post/page', async () => {
		await page.goto( `${ config.get( 'url' ) }wp-admin/post-new.php` );
		await uiLoaded();

		const closeWelcomeModal = await page.$( 'button[aria-label="Close"]' );
		if ( closeWelcomeModal ) {
			await closeWelcomeModal.click();
		}

		await page.click( 'button[aria-label="Add block"]' );

		const searchInput = await page.waitForSelector(
			'input.components-search-control__input'
		);
		searchInput.type( 'switcher', { delay: 20 } );

		await page.waitForSelector( 'button[role="option"]' );
		await expect( page ).toMatchElement( 'button[role="option"]', {
			text: 'Currency Switcher Block',
		} );
		await page.waitFor( 1000 );
	} );
} );
