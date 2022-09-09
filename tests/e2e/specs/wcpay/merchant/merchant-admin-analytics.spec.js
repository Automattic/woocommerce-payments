/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, takeScreenshot } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

describe( 'Admin Order Analytics', () => {
	beforeAll( async () => {
		// Place an order to ensure the analytics data is correct.
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		const card = config.get( 'cards.basic' );
		await fillCardDetails( page, card );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		// Login
		await merchant.login();

		// // Go to Action Scheduler
		// await merchantWCP.openActionScheduler();

		// // Filter results by pending
		// await page.click( 'ul.subsubsub > .pending > a' );
		// await page.waitForNavigation( { waitUntil: 'networkidle0' } );

		// // Run the Action Scheduler task to update the order stats
		// await evalAndClick( 'div.row-actions > span.run > a' );
		// await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		// await expect( page ).toMatchElement(
		// 	'div#message.updated > p > strong',
		// 	{
		// 		text: actionSchedulerHook,
		// 	}
		// );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'page should load without any errors', async () => {
		await merchantWCP.openOrderAnalytics();
		await expect( page ).toMatchElement( 'h2', {
			text: 'Orders',
		} );
		await takeScreenshot( 'merchant-admin-order-analytics' );
	} );

	it( 'orders table should have customer currency column', async () => {
		await merchantWCP.openOrderAnalytics();
		await expect( page ).toMatchElement( 'span', {
			text: 'Customer currency',
		} );
		await takeScreenshot( 'merchant-admin-order-analytics' );
	} );
} );
