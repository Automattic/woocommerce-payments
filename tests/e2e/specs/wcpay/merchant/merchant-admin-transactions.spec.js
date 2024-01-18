/**
 * External dependencies
 */
import config from 'config';
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import {
	RUN_SUBSCRIPTIONS_TESTS,
	merchantWCP,
	takeScreenshot,
	describeif,
} from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const card = config.get( 'cards.basic' );
const product1 = config.get( 'products.simple.name' );

describe( 'Admin transactions', () => {
	beforeAll( async () => {
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' ),
			[ [ product1, 1 ] ]
		);
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await merchant.login();
	}, 200000 );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openTransactions();
		await jestPuppeteer.debug();
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
