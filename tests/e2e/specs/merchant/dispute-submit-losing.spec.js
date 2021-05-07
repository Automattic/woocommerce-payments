/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
	uiUnblocked,
} = require('@woocommerce/e2e-utils');

/**
 * Internal dependencies
 */
import {
	fillCardDetails,
	setupProductCheckout,
} from '../../utils/payments';
import {
	merchantWCP
} from '../../utils/flows';

let orderId;

describe('Disputes > Submit winning dispute', () => {
	beforeAll(async () => {
		await page.goto(config.get('url'), {waitUntil: 'networkidle0'});

		// Place an order to use for a dispute
		await setupProductCheckout(config.get('addresses.customer.billing'));
		const card = config.get('cards.disputed-unreceived');
		await fillCardDetails(page, card);
		await shopper.placeOrder();
		await expect(page).toMatch('Order received');

		// Get the order ID so we can open it in the merchant view
		const orderIdField = await page.$('.woocommerce-order-overview__order.order > strong');
		orderId = await orderIdField.evaluate((el) => el.innerText);
	});

	it('should process a losing dispute', async () => {
		await merchant.login();
		await merchantWCP.openDisputes();

		await expect(page).toClick('td.woocommerce-table__item > a', {text: orderId});
	});
});
