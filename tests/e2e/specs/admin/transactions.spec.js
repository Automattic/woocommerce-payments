/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import { merchant } from '../../utils/flows';

describe( 'Admin transactions', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await merchant.openTransactions();
	} );
} );
