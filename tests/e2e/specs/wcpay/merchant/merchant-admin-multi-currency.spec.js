/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, takeScreenshot } from '../../../utils';
import { uiLoaded } from '../../../utils/helpers';

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
		await page.goto( `${ config.get( 'url' ) }wp-admin/widgets.php`, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();
		await page.click( 'button[aria-label="Add block"]' );

		const searchInput = await page.waitForSelector(
			'input.components-search-control__input'
		);
		searchInput.type( 'switcher', { delay: 20 } );
		await expect( page ).toMatchElement( 'button[role="option"]', {
			text: 'Currency Switcher Widget',
		} );
	} );
} );
