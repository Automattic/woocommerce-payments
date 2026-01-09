/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	disableCardTestingProtection,
	enableCardTestingProtection,
} from '../../../utils/devtools';
import { activateTheme } from '../../../utils/merchant';
import { config } from '../../../config/default';
import {
	addToCartFromShopPage,
	confirmCardAuthentication,
	emptyCart,
	expectFraudPreventionToken,
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../../utils/shopper';

/**
 * Tests for successful purchases with both card testing prevention enabled
 * and disabled states using a site builder enabled theme.
 */
test.describe(
	'Successful purchase, site builder theme',
	{ tag: '@shopper' },
	() => {
		let merchantContext: BrowserContext;
		let merchantPage: Page;
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			merchantContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'admin' ),
			} );
			merchantPage = await merchantContext.newPage();

			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();

			await activateTheme( 'twentytwentyfour' );
		} );

		test.afterAll( async () => {
			await emptyCart( shopperPage );
			await activateTheme( 'storefront' );
			await disableCardTestingProtection();
			await merchantContext?.close();
			await shopperContext?.close();
		} );

		[ true, false ].forEach( ( cardTestingPreventionEnabled ) => {
			test.describe(
				`card prevention: ${ cardTestingPreventionEnabled }`,
				() => {
					test.beforeAll( async () => {
						if ( cardTestingPreventionEnabled ) {
							await enableCardTestingProtection();
						} else {
							await disableCardTestingProtection();
						}
					} );

					test.beforeEach( async () => {
						await emptyCart( shopperPage );
						await addToCartFromShopPage( shopperPage );
						await setupCheckout(
							shopperPage,
							config.addresses.customer.billing
						);
					} );

					const runPurchaseFlow = async (
						page: Page,
						card: typeof config.cards.basic,
						is3dsCard: boolean
					) => {
						await expectFraudPreventionToken(
							page,
							cardTestingPreventionEnabled
						);
						await fillCardDetails( page, card );
						await placeOrder( page );
						if ( is3dsCard ) {
							await confirmCardAuthentication( page );
						}
						await page.waitForURL( /\/order-received\//, {
							waitUntil: 'load',
						} );
						expect( page.url() ).toMatch(
							/checkout\/order-received\/\d+\//
						);
					};

					test( `basic card`, async () => {
						await runPurchaseFlow(
							shopperPage,
							config.cards.basic,
							false
						);
					} );

					test( `3DS card`, async () => {
						await runPurchaseFlow(
							shopperPage,
							config.cards[ '3ds' ],
							true
						);
					} );
				}
			);
		} );
	}
);
