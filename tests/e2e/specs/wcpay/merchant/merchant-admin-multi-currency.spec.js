/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchantWCP, takeScreenshot } from '../../../utils';

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
} );
