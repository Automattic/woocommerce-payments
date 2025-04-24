/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	disableCardTestingProtection,
	enableCardTestingProtection,
} from '../../../utils/devtools';
import { getMerchant, getShopper } from '../../../utils/helpers';
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
test.describe( 'Successful purchase, site builder theme', () => {
	let merchantPage: Page;
	let shopperPage: Page;

	test.beforeAll( async ( { browser } ) => {
		const merchant = await getMerchant( browser );
		const shopper = await getShopper( browser );
		merchantPage = merchant.merchantPage;
		shopperPage = shopper.shopperPage;

		await activateTheme( merchantPage, 'twentytwentyfour' );
	} );

	test.afterAll( async () => {
		await emptyCart( shopperPage );
		await activateTheme( merchantPage, 'storefront' );
		await disableCardTestingProtection( merchantPage );
	} );

	[ true, false ].forEach( ( cardTestingPreventionEnabled ) => {
		test.describe(
			`card prevention: ${ cardTestingPreventionEnabled }`,
			() => {
				test.beforeAll( async () => {
					if ( cardTestingPreventionEnabled ) {
						await enableCardTestingProtection( merchantPage );
					} else {
						await disableCardTestingProtection( merchantPage );
					}
				} );

				test.beforeEach( async () => {
					// Reset cart & checkout setup
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
} );
