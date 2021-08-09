/**
 * External dependencies
 */
const { merchant } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	RUN_SUBSCRIPTIONS_TESTS,
	merchantWCP,
	takeScreenshot,
	describeif,
} from '../../utils';

describe( 'Admin transactions', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openTransactions();
		await expect( page ).toMatchElement( 'h2', { text: 'Transactions' } );
		await takeScreenshot( 'merchant-admin-transactions' );
	} );
} );

describeif( RUN_SUBSCRIPTIONS_TESTS, () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openTransactions();
		await expect( page ).toContain( { text: 'Subscription #' } );
		await takeScreenshot(
			'merchant-admin-transactions-subscriptions-active'
		);
	} );
} );
