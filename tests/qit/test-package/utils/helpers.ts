/**
 * External dependencies
 */
import { test, Page, Browser, BrowserContext, expect } from '@playwright/test';

/**
 * Returns an anonymous shopper page and context.
 * Emulates a new shopper who has not been authenticated and has no previous state, e.g. cart, order, etc.
 */
export const getAnonymousShopper = async (
	browser: Browser
): Promise< {
	shopperPage: Page;
	shopperContext: BrowserContext;
} > => {
	const shopperContext = await browser.newContext();
	const shopperPage = await shopperContext.newPage();
	return { shopperPage, shopperContext };
};

/**
 * Conditionally determine whether or not to skip a test suite.
 */
export const describeif = ( condition: boolean ) =>
	condition ? test.describe : test.describe.skip;

export const isUIUnblocked = async ( page: Page ) => {
	await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
};

export const checkPageExists = async (
	page: Page,
	pageUrl: string
): Promise< boolean > => {
	// Check whether specified page exists
	return page
		.goto( pageUrl, {
			waitUntil: 'load',
		} )
		.then( ( response ) => {
			if ( response.status() === 404 ) {
				return false;
			}
			return true;
		} )
		.catch( () => {
			return false;
		} );
};
