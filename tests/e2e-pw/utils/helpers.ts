/* eslint-disable no-console */
/**
 * External dependencies
 */
import path from 'path';
import {
	test,
	Page,
	Browser,
	BrowserContext,
	expect,
	FullProject,
} from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../config/default';
import RestAPI from './rest-api';
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
): Promise< void > => {
	await page.goto( '/wp-admin' );

	await page.getByLabel( 'Username or Email Address' ).fill( user.username );

	// Need exact match to avoid resolving "Show password" button.
	const passwordInput = page.getByLabel( 'Password', { exact: true } );

	// The focus is used to avoid the password being filled in the username field.
	await passwordInput.focus();
	await passwordInput.fill( user.password );

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
	browser: Browser,
	asNewCustomer = false,
	baseURL = '' // Needed for recreating customer
): Promise< {
	shopperPage: Page;
	shopperContext: BrowserContext;
} > => {
	if ( asNewCustomer ) {
		const restApi = new RestAPI( baseURL );
		await restApi.recreateCustomer(
			config.users.customer,
			config.addresses.customer.billing,
			config.addresses.customer.shipping
		);

		const shopperContext = await browser.newContext();
		const shopperPage = await shopperContext.newPage();
		await wpAdminLogin( shopperPage, config.users.customer );
		await shopperPage.waitForLoadState( 'networkidle' );
		await shopperPage.goto( '/my-account' );
		expect(
			shopperPage.locator(
				'.woocommerce-MyAccount-navigation-link--customer-logout'
			)
		).toBeVisible();
		await expect(
			shopperPage.locator(
				'div.woocommerce-MyAccount-content > p >> nth=0'
			)
		).toContainText( 'Hello' );
		await shopperPage
			.context()
			.storageState( { path: customerStorageFile } );
		return { shopperPage, shopperContext };
	}
	const shopperContext = await browser.newContext( {
		storageState: customerStorageFile,
	} );
	const shopperPage = await shopperContext.newPage();
	return { shopperPage, shopperContext };
};

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

export const isCustomerLoggedIn = async ( page: Page ) => {
	await page.goto( '/my-account' );
	const logoutLink = page.locator(
		'.woocommerce-MyAccount-navigation-link--customer-logout'
	);

	return await logoutLink.isVisible();
};

export const loginAsCustomer = async (
	page: Page,
	customer: { username: string; password: string }
) => {
	let customerLoggedIn = false;
	const customerRetries = 5;

	for ( let i = 0; i < customerRetries; i++ ) {
		try {
			// eslint-disable-next-line no-console
			console.log( 'Trying to log-in as customer...' );
			await wpAdminLogin( page, customer );

			await page.goto( '/my-account' );
			await expect(
				page.locator(
					'.woocommerce-MyAccount-navigation-link--customer-logout'
				)
			).toBeVisible();
			await expect(
				page.locator( 'div.woocommerce-MyAccount-content > p >> nth=0' )
			).toContainText( 'Hello' );

			console.log( 'Logged-in as customer successfully.' );
			customerLoggedIn = true;
			break;
		} catch ( e ) {
			console.log(
				`Customer log-in failed. Retrying... ${ i }/${ customerRetries }`
			);
			console.log( e );
		}
	}

	if ( ! customerLoggedIn ) {
		throw new Error(
			'Cannot proceed e2e test, as customer login failed. Please check if the test site has been setup correctly.'
		);
	}

	await page.context().storageState( { path: customerStorageFile } );
};

/**
 * Adds a special cookie during the session to avoid the support session detection page.
 * This is temporarily displayed when navigating to the login page while Jetpack SSO and protect modules are disabled.
 * Relevant for Atomic sites only.
 */
export const addSupportSessionDetectedCookie = async (
	page: Page,
	project: FullProject
) => {
	if ( process.env.NODE_ENV !== 'atomic' ) return;

	const domain = new URL( project.use.baseURL ).hostname;

	await page.context().addCookies( [
		{
			value: 'true',
			name: '_wpcomsh_support_session_detected',
			path: '/',
			domain,
		},
	] );
};

export const ensureCustomerIsLoggedIn = async (
	page: Page,
	project: FullProject
) => {
	if ( ! ( await isCustomerLoggedIn( page ) ) ) {
		await addSupportSessionDetectedCookie( page, project );
		await loginAsCustomer( page, config.users.customer );
	}
};
