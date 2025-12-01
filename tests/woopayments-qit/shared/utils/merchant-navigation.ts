/**
 * External dependencies
 */
import { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { dataHasLoaded } from './merchant';

export const goToOrders = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/admin.php?page=wc-orders', {
		waitUntil: 'load',
	} );
	await expect( page.locator( 'h1.wp-heading-inline' ) ).toContainText(
		'Orders'
	);
};

export const goToOrder = async (
	page: Page,
	orderId: string
): Promise< void > => {
	await page.goto(
		`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }`,
		{
			waitUntil: 'load',
		}
	);
};

export const goToWooCommerceSettings = async (
	page: Page,
	tab = 'general'
): Promise< void > => {
	await page.goto( `/wp-admin/admin.php?page=wc-settings&tab=${ tab }`, {
		waitUntil: 'load',
	} );
};

export const goToWooPaymentsSettings = async (
	page: Page
): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToTransactions = async ( page: Page ): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToDeposits = async ( page: Page ): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdeposits',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToDisputes = async ( page: Page ): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fdisputes',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToMultiCurrencySettings = async (
	page: Page
): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToSubscriptions = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/admin.php?page=wc-orders--shop_subscription', {
		waitUntil: 'load',
	} );
};

export const goToSubscriptionPage = async (
	page: Page,
	subscriptionId: number
): Promise< void > => {
	await goToSubscriptions( page );
	await page.getByRole( 'link', { name: `#${ subscriptionId }` } ).click();
	await dataHasLoaded( page );
};

export const goToPaymentDetails = async (
	page: Page,
	paymentIntentId: string
): Promise< void > => {
	await page.goto(
		`/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Ftransactions%2Fdetails&id=${ paymentIntentId }`,
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToOptionsPage = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/options.php', {
		waitUntil: 'load',
	} );
};

export const goToActionScheduler = async (
	page: Page,
	status?: string,
	search?: string
): Promise< void > => {
	let pageUrl = '/wp-admin/tools.php?page=action-scheduler';
	if ( status ) {
		pageUrl += `&status=${ status }`;
	}
	if ( search ) {
		pageUrl += `&s=${ search }`;
	}
	await page.goto( pageUrl, {
		waitUntil: 'load',
	} );
};

export const goToOrderAnalytics = async ( page: Page ): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fanalytics%2Forders',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToWidgets = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/widgets.php', {
		waitUntil: 'load',
	} );
};

export const goToNewPost = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/post-new.php', {
		waitUntil: 'load',
	} );
};

export const goToThemes = async ( page: Page ): Promise< void > => {
	await page.goto( '/wp-admin/themes.php', {
		waitUntil: 'load',
	} );
};

export const goToMultiCurrencyOnboarding = async (
	page: Page
): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=%2Fpayments%2Fmulti-currency-setup',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};

export const goToConnect = async ( page: Page ): Promise< void > => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-admin&path=/payments/connect',
		{ waitUntil: 'load' }
	);
	await dataHasLoaded( page );
};
