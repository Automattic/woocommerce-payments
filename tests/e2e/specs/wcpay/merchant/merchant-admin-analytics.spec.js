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

const actionSchedulerHook = 'wc-admin_import_orders';

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
		const orderCell = await page.$(
			'.woocommerce-order-overview__order > strong'
		);
		const orderId = await page.evaluate(
			( order ) => order.innerText,
			orderCell
		);

		// Login
		await merchant.login();

		// Go to Action Scheduler
		await merchantWCP.openActionScheduler( 'pending', orderId );
		const importOrderRun = await page.$x(
			'//code[contains(text(), "0 => ' +
				orderId +
				'")]/ancestor::tr//span[contains(@class, "run")]/a'
		);
		// Run the Action Scheduler task to update the order stats
		await importOrderRun[ 0 ].evaluate( ( link ) => link.click() );

		await page.waitForNavigation( { waitUntil: 'networkidle0' } );
		await expect( page ).toMatchElement(
			'div#message.updated > p > strong',
			{
				text: actionSchedulerHook,
			}
		);
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
