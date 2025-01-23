/**
 * External dependencies
 */
import { Page } from 'playwright/test';
/**
 * Internal dependencies
 */
import { isUIUnblocked } from './shopper';

export const goToShop = async ( page: Page, pageNumber?: number ) => {
	if ( pageNumber ) {
		await page.goto( `/shop/page/` + pageNumber, { waitUntil: 'load' } );
	} else {
		await page.goto( `/shop/`, { waitUntil: 'load' } );
	}
};

export const goToShopWithCurrency = async ( page: Page, currency: string ) => {
	await page.goto( `/shop/?currency=${ currency }`, { waitUntil: 'load' } );
};

export const goToProductPageBySlug = async (
	page: Page,
	productSlug: string
) => {
	await page.goto( `/product/${ productSlug }`, { waitUntil: 'load' } );
};

export const goToCart = async ( page: Page ) => {
	await page.goto( '/cart/', { waitUntil: 'load' } );
	await isUIUnblocked( page );
};

export const goToCheckout = async ( page: Page ) => {
	await page.goto( '/checkout/', { waitUntil: 'load' } );
	await isUIUnblocked( page );
};

export const goToOrders = async ( page: Page ) => {
	await page.goto( '/my-account/orders/', {
		waitUntil: 'load',
	} );
};

export const goToOrder = async ( page: Page, orderId: string ) => {
	await page.goto( `/my-account/view-order/${ orderId }`, {
		waitUntil: 'load',
	} );
};

export const goToMyAccount = async ( page: Page, subPage?: string ) => {
	await page.goto( '/my-account/' + ( subPage ?? '' ), {
		waitUntil: 'load',
	} );
};

export const goToSubscriptions = async ( page: Page ) =>
	await goToMyAccount( page, 'subscriptions' );
