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
[ true, false ].forEach( ( cardTestingPreventionEnabled ) => {
	test.describe( 'Successful purchase, site builder theme', () => {
		let merchantPage: Page = null,
			shopperPage: Page = null;
		test.beforeAll( async ( { browser } ) => {
			merchantPage = ( await getMerchant( browser ) ).merchantPage;
			shopperPage = ( await getShopper( browser ) ).shopperPage;
			await activateTheme( merchantPage, 'twentytwentyfour' );
			if ( cardTestingPreventionEnabled ) {
				await enableCardTestingProtection( merchantPage );
			}
		} );

		test.afterAll( async () => {
			await emptyCart( shopperPage );
			await activateTheme( merchantPage, 'storefront' );
			if ( cardTestingPreventionEnabled ) {
				await disableCardTestingProtection( merchantPage );
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

		const sharedTestMethod = async (
			page: Page,
			card: typeof config.cards.basic,
			threeDSenabled: boolean,
			cardTestingFlag: boolean
		) => {
			await expectFraudPreventionToken( page, cardTestingFlag );
			await fillCardDetails( page, card );
			await placeOrder( page );
			if ( threeDSenabled ) {
				await confirmCardAuthentication( page );
			}
			// This is required because different themes have different strings than "Order received".
			await page.waitForURL( /\/order-received\//, {
				waitUntil: 'load',
			} );
			expect( page.url() ).toMatch( /checkout\/order-received\/\d+\// );
		};

		test( `using a basic card, carding prevention ${ cardTestingPreventionEnabled }`, async () => {
			await sharedTestMethod(
				shopperPage,
				config.cards.basic,
				false,
				cardTestingPreventionEnabled
			);
		} );

		test( `using a 3DS card, carding prevention ${ cardTestingPreventionEnabled }`, async () => {
			await sharedTestMethod(
				shopperPage,
				config.cards[ '3ds' ],
				true,
				cardTestingPreventionEnabled
			);
		} );
	} );
} );
