/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { StoreOwnerFlow } from '../../utils';

const WCPAY_DEPOSITS =
	config.get( 'url' ) +
	'wp-admin/admin.php?page=wc-admin&path=/payments/deposits';

describe( 'Admin deposits', () => {
	beforeAll( async () => {
		await StoreOwnerFlow.login();
	} );

	it( 'page should load without any errors', async () => {
		await page.goto( WCPAY_DEPOSITS, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', {
			text: 'Deposit history',
		} );
	} );
} );
