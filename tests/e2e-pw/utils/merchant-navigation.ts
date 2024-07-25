/**
 * External dependencies
 */
import { Page } from 'playwright/test';

export const goToOrder = async ( page: Page, orderId: string ) => {
	await page.goto( `/wp-admin/post.php?post=${ orderId }&action=edit` );
};

export const goToWooPaymentsSettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=checkout&section=woocommerce_payments'
	);
};

export const goToMultiCurrencySettings = async ( page: Page ) => {
	await page.goto(
		'/wp-admin/admin.php?page=wc-settings&tab=wcpay_multi_currency',
		{ waitUntil: 'load' }
	);
};

export const goToWidgets = async ( page: Page ) => {
	await page.goto( '/wp-admin/widgets.php', {
		waitUntil: 'load',
	} );
};

export const goToNewPost = async ( page: Page ) => {
	await page.goto( '/wp-admin/post-new.php', {
		waitUntil: 'load',
	} );
};
