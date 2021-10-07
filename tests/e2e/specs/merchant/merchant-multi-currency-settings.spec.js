/**
 * External dependencies
 */
import config from 'config';

const { merchant, shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, takeScreenshot } from '../../utils';

describe( 'Multi-Currency settings', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openMultiCurrencySettings();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Enabled currencies',
		} );
		await takeScreenshot( 'merchant-multi-currency-settings' );
	} );
} );
