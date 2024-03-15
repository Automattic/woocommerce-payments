/**
 * External dependencies
 */
import path from 'path';
import { test, Page, Browser, BrowserContext } from '@playwright/test';

export const merchantStorageFile = path.resolve(
	__dirname,
	'../.auth/merchant.json'
);

export const customerStorageFile = path.resolve(
	__dirname,
	'../.auth/customer.json'
);

/**
 * Logs in to the WordPress admin as a given user.
 */
export const wpAdminLogin = async (
	page: Page,
	user: { username: string; password: string }
): void => {
	await page.goto( `/wp-admin` );
	await page.getByLabel( 'Username or Email Address' ).fill( user.username );
	await page.getByLabel( 'Password' ).fill( user.password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
};

/**
 * Sets the shopper as the authenticated user for a test suite (describe).
 */
export const useShopper = (): void => {
	test.use( {
		storageState: customerStorageFile,
	} );
};

/**
 * Sets the merchant as the authenticated user for a test suite (describe).
 */
export const useMerchant = (): void => {
	test.use( {
		storageState: merchantStorageFile,
	} );
};

/**
 * Returns the merchant authenticated page and context.
 * Allows switching between merchant and shopper contexts within a single test.
 */
export const getMerchant = async (
	browser: Browser
): Promise< {
	merchantPage: Page;
	merchantContext: BrowserContext;
} > => {
	const merchantContext = await browser.newContext( {
		storageState: merchantStorageFile,
	} );
	const merchantPage = await merchantContext.newPage();
	return { merchantPage, merchantContext };
};

/**
 * Returns the shopper authenticated page and context.
 * Allows switching between merchant and shopper contexts within a single test.
 */
export const getShopper = async (
	browser: Browser
): Promise< {
	shopperPage: Page;
	shopperContext: BrowserContext;
} > => {
	const shopperContext = await browser.newContext( {
		storageState: customerStorageFile,
	} );
	const shopperPage = await shopperContext.newPage();
	return { shopperPage, shopperContext };
};
