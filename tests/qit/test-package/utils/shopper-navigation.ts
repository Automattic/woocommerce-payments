/**
 * External dependencies
 */
import { Page } from 'playwright/test';
import qit from '@qit/helpers';

/**
 * Internal dependencies
 */
import { isUIUnblocked } from './helpers';
import { config } from '../config/default';

/**
 * Helper to ensure customer is logged in after navigating to a protected page.
 * If a login form is shown, re-authenticate and navigate again.
 */
const ensureAuthAfterNavigation = async (
	page: Page,
	targetUrl: string
): Promise< void > => {
	const loginForm = page.locator( 'form.woocommerce-form-login' );
	const loginFormVisible = await loginForm.isVisible().catch( () => false );

	if ( loginFormVisible ) {
		// Re-login the customer
		const { username, password } = config.users.customer;
		await qit.loginAs( page, username, password );
		await page.waitForLoadState( 'domcontentloaded' );
		// Navigate to the intended page after re-auth
		await page.goto( targetUrl, { waitUntil: 'load' } );
	}
};

export const goToShop = async (
	page: Page,
	{ pageNumber, currency }: { pageNumber?: number; currency?: string } = {}
) => {
	let url = '/shop/';

	if ( pageNumber ) {
		url += `page/${ pageNumber }/`;
	}

	if ( currency ) {
		url += `?currency=${ currency }`;
	}

	await page.goto( url, { waitUntil: 'load' } );
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

export const goToCheckout = async (
	page: Page,
	{ currency }: { currency?: string } = {}
) => {
	let url = '/checkout/';

	if ( currency ) {
		url += `?currency=${ currency }`;
	}

	await page.goto( url, { waitUntil: 'load' } );
	await isUIUnblocked( page );
};

export const goToCheckoutWCB = async ( page: Page ) => {
	await page.goto( '/checkout-wcb', {
		waitUntil: 'load',
	} );
	// since the block-based checkout page has a few async things, we need to wait for the UI to be fully rendered.
	await page
		.getByRole( 'heading', { name: 'Contact information' } )
		.waitFor( { state: 'visible' } );
};

export const goToOrders = async ( page: Page ) => {
	const url = '/my-account/orders/';
	await page.goto( url, { waitUntil: 'load' } );
	await ensureAuthAfterNavigation( page, url );
};

export const goToOrder = async ( page: Page, orderId: string ) => {
	const url = `/my-account/view-order/${ orderId }`;
	await page.goto( url, { waitUntil: 'load' } );
	await ensureAuthAfterNavigation( page, url );
};

export const goToMyAccount = async ( page: Page, subPage?: string ) => {
	const url = '/my-account/' + ( subPage ?? '' );
	await page.goto( url, { waitUntil: 'load' } );
	await ensureAuthAfterNavigation( page, url );
};

export const goToSubscriptions = async ( page: Page ) =>
	await goToMyAccount( page, 'subscriptions' );
