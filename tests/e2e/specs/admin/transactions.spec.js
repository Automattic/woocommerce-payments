/**
 * External dependencies
 */
import config from 'config';

const { merchant } = require( '@woocommerce/e2e-utils' );

const WCPAY_TRANSACTIONS =
	config.get( 'url' ) +
	'wp-admin/admin.php?page=wc-admin&path=/payments/transactions';

describe( 'Admin transactions', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await page.goto( WCPAY_TRANSACTIONS, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', { text: 'Transactions' } );
	} );
} );
