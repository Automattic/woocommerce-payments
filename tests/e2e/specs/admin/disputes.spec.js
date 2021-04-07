/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { StoreOwnerFlow } from '../../utils';

const WCPAY_DISPUTES =
	config.get( 'url' ) +
	'wp-admin/admin.php?page=wc-admin&path=/payments/disputes';

describe( 'Admin disputes', () => {
	beforeAll( async () => {
		await StoreOwnerFlow.login();
	} );

	it( 'page should load without any errors', async () => {
		await page.goto( WCPAY_DISPUTES, {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement( 'h2', { text: 'Disputes' } );
	} );
} );
