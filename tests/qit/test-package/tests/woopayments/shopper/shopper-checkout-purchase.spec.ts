/**
 * External dependencies
 */
import { Page, BrowserContext } from '@playwright/test';
import { test, expect, getAuthState } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */

import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import * as devtools from '../../../utils/devtools';

test.describe( 'Successful purchase', { tag: '@shopper' }, () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let merchantContext: BrowserContext;
	let shopperContext: BrowserContext;

	for ( const ctpEnabled of [ false, true ] ) {
		test.describe( `Carding protection ${ ctpEnabled }`, () => {
			test.beforeAll( async ( { browser } ) => {
				merchantContext = await browser.newContext( {
					storageState: await getAuthState( browser, 'admin' ),
				} );
				merchantPage = await merchantContext.newPage();

				shopperContext = await browser.newContext( {
					storageState: await getAuthState( browser, 'customer' ),
				} );
				shopperPage = await shopperContext.newPage();
				if ( ctpEnabled ) {
					await devtools.enableCardTestingProtection();
				}
			} );

			test.afterAll( async () => {
				if ( ctpEnabled ) {
					await devtools.disableCardTestingProtection();
				}
				await merchantContext?.close();
				await shopperContext?.close();
			} );

			test.beforeEach( async () => {
				await shopper.emptyCart( shopperPage );
				await shopper.addToCartFromShopPage( shopperPage );
				await shopper.setupCheckout(
					shopperPage,
					config.addresses.customer.billing
				);
				await shopper.expectFraudPreventionToken(
					shopperPage,
					ctpEnabled
				);
			} );

			test( 'using a basic card', { tag: '@critical' }, async () => {
				await shopper.fillCardDetails( shopperPage );
				await shopper.placeOrder( shopperPage );

				await expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();
			} );

			test( 'using a 3DS card', { tag: '@critical' }, async () => {
				await shopper.fillCardDetails(
					shopperPage,
					config.cards[ '3ds' ]
				);
				await shopper.placeOrder( shopperPage );
				await shopper.confirmCardAuthentication( shopperPage );
				await expect(
					shopperPage.getByRole( 'heading', {
						name: 'Order received',
					} )
				).toBeVisible();
			} );
		} );
	}
} );
