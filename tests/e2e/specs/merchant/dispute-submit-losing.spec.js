/**
 * External dependencies
 */
import config from 'config';

const {
	merchant,
	shopper,
} = require('@woocommerce/e2e-utils');

/**
 * Internal dependencies
 */
import {
	fillCardDetails,
	setupProductCheckout,
	merchantWCP,
} from '../../utils';

let orderId;

describe('Disputes > Submit winning dispute', () => {
	beforeAll(async () => {
		await page.goto(config.get('url'), { waitUntil: 'networkidle0' });

		// Place an order for a dispute
		await setupProductCheckout(
			config.get('addresses.customer.billing')
		);
		const card = config.get('cards.disputed-unreceived');
		await fillCardDetails(page, card);
		await shopper.placeOrder();
		await expect(page).toMatch('Order received');

		// Get the order ID so we can open it in the merchant view
		const orderIdField = await page.$(
			'.woocommerce-order-overview__order.order > strong'
		);
		orderId = await orderIdField.evaluate((el) => el.innerText);
	});

	it('should process a losing dispute', async () => {
		await merchant.login();
		await merchantWCP.openDisputes();

		// Verify a dispute is present with a proper ID and open it
		await expect(page).toMatchElement('.woocommerce-table__item > a', { text: orderId });
		await expect(page).toClick('.woocommerce-table__item > a');

		// Verify there is a content present
		await expect(page).toMatchElement('div.wcpay-dispute-details');
		await expect(page).toMatchElement('.components-card__header', { text: "Dispute overview" });
		await expect(page).toMatchElement('.components-card__header', { text: "Dispute: Product not received" });

		// Accept the dispute
		await expect(page).toClick('button.components-button', { text: "Accept dispute" });
		await page.on("dialog", (dialog) => { dialog.accept() });

		// Verify the dispute has been accepted properly
		await expect(page).toMatchElement('span.chip .chip-light .is-compact', { text: "Lost" });
		await expect(page).toClick('.woocommerce-table__item > a');
		await expect(page).not.toMatchElement('button.components-button', { text: "Challenge dispute" });
		await expect(page).not.toMatchElement('button.components-button', { text: "Accept dispute" });
	});
});
