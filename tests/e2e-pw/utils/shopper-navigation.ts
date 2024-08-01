/**
 * External dependencies
 */
import { Page } from 'playwright/test';

export const goToShopWithCurrency = async ( page: Page, currency: string ) => {
	await page.goto( `/?currency=${ currency }`, { waitUntil: 'load' } );
};

export const goToProductPageBySlug = async (
	page: Page,
	productSlug: string
) => {
	await page.goto( `/product/${ productSlug }`, { waitUntil: 'load' } );
};
