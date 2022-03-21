/**
 * External dependencies
 */
import config from 'config';

const { merchant } = require( '@woocommerce/e2e-utils' );

const WCADMIN_GATEWAYS_LIST = `${ config.get(
	'url'
) }wp-admin/admin.php?page=wc-settings&tab=checkout&section`;

const WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE =
	'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled';

describe( 'payment gateways disable confirmation', () => {
	beforeAll( async () => {
		await merchant.login();
	} );

	beforeEach( async () => {
		await page.goto( WCADMIN_GATEWAYS_LIST, {
			waitUntil: 'networkidle0',
		} );
	} );

	afterAll( async () => {
		await merchant.logout();
	} );

	it( 'should show the confirmation dialog when disabling WCPay', async () => {
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);

		// Dialog should be displayed
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );

		// Clicking "Cancel" should not disable WCPay
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );

		// After clicking "Cancel", the modal should close and WCPay should still be enabled, even after refresh
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);
	} );

	it( 'should disable WCPay after confirming, then enable again without confirming', async () => {
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);

		// Dialog should be displayed
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );

		// Clicking "Disable" should disable WCPay
		await expect( page ).toClick( 'button', {
			text: 'Disable',
		} );

		// After clicking "Disable", the modal should close
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );

		await page.waitForSelector(
			`${ WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE } .woocommerce-input-toggle:not(.woocommerce-input-toggle--loading)`
		);

		// and refreshing the page should show WCPay become disabled
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently disabled']`,
			{ text: 'No' }
		);

		// now we can re-enable it with no issues
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);
		await page.waitForSelector(
			`${ WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE } .woocommerce-input-toggle:not(.woocommerce-input-toggle--loading)`
		);
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);
	} );

	it( 'should show the modal even after clicking the cancel button multiple times', async () => {
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);

		// Dialog should be displayed
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );

		// Clicking "Cancel" should not disable WCPay
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );

		// After clicking "Cancel", the modal should close and WCPay should still be enabled
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
		await expect(
			page
		).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`,
			{ text: 'Yes' }
		);

		// trying again to disable it - the modal should display again
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick(
			WC_GATEWAYS_LIST_TABLE__WC_PAYMENTS_TOGGLE
		);
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
	} );
} );
