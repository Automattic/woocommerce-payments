/**
 * External dependencies
 */
import config from 'config';

const { merchant } = require( '@woocommerce/e2e-utils' );

const WCADMIN_GATEWAYS_LIST = `${ config.get(
	'url'
) }wp-admin/admin.php?page=wc-settings&tab=checkout&section`;

const WCPAY_DEV_TOOLS = `${ config.get(
	'url'
) }wp-admin/admin.php?page=wcpaydev`;

describe( 'payment gateways disable confirmation', () => {
	beforeAll( async () => {
		await merchant.login();

		await page.goto( WCPAY_DEV_TOOLS, {
			waitUntil: 'networkidle0',
		} );

		// Enable the "Enable grouped settings" checkbox and save
		await expect( page ).toClick( 'label', {
			text: 'Enable grouped settings',
		} );
		await expect( page ).toClick( 'input[type="submit"]' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
	} );

	beforeEach( async () => {
		await page.goto( WCADMIN_GATEWAYS_LIST, {
			waitUntil: 'networkidle0',
		} );
	} );

	afterAll( async () => {
		// Disable the "Enable grouped settings" checkbox and save
		await expect( page ).toClick( 'label', {
			text: 'Enable grouped settings',
		} );
		await expect( page ).toClick( 'input[type="submit"]' );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );

		await merchant.logout();
	} );

	it( 'should show the confirmation dialog when disabling WCPay', async () => {
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
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
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);
	} );

	it( 'should allow disabling WCPay', async () => {
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);

		// Dialog should be displayed
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );

		// Clicking "Disable" should disable WCPay
		await expect( page ).toClick( 'button', {
			text: 'Disable',
		} );

		// After clicking "Disable", the modal should close
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );

		// and refreshing the page should show WCPay become disabled
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently disabled']`
		);

		// now we can re-enable it with no issues
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);
		await expect( page ).toClick( 'button', {
			text: 'Save changes',
		} );
		await page.waitForNavigation( {
			waitUntil: 'networkidle0',
		} );
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);
	} );

	it( 'should show the modal even after cancelling the action one time', async () => {
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);

		// Click the "Disable WCPay" toggle button
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);

		// Dialog should be displayed
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );

		// Clicking "Cancel" should not disable WCPay
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );

		// After clicking "Cancel", the modal should close and WCPay should still be enabled
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toMatchElement(
			`[aria-label='The "WooCommerce Payments" payment method is currently enabled']`
		);

		// trying again to disable it - the modal should display again
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick(
			'tr[data-gateway_id="woocommerce_payments"] .wc-payment-gateway-method-toggle-enabled'
		);
		await expect( page ).toMatch( 'Disable WooCommerce Payments' );
		await expect( page ).toClick( 'button', {
			text: 'Cancel',
		} );
		await expect( page ).not.toMatch( 'Disable WooCommerce Payments' );
	} );
} );
