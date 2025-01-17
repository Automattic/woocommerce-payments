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
} from '../../utils/devtools';
import { getMerchant, getShopper } from '../../utils/helpers';
import { activateTheme } from '../../utils/merchant';
import { config } from '../../config/default';
import {
	addCartProduct,
	confirmCardAuthentication,
	emptyCart,
	fillCardDetails,
	placeOrder,
	setupCheckout,
} from '../../utils/shopper';
import { goToShop } from '../../utils/shopper-navigation';

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
			await goToShop( shopperPage );
			await addCartProduct( shopperPage );
			await setupCheckout(
				shopperPage,
				config.addresses.customer.billing
			);
		} );

		const sharedTestMethod = async (
			page: Page,
			card: any,
			threeDSenabled: boolean,
			cardTestingFlag: boolean
		) => {
			const token = await page.evaluate( () => {
				return ( window as any ).wcpayFraudPreventionToken;
			} );
			if ( cardTestingFlag ) {
				expect( token ).toBeDefined();
			} else {
				expect( token ).toBeUndefined();
			}
			await fillCardDetails( page, card );
			await placeOrder( page );
			if ( threeDSenabled ) {
				await confirmCardAuthentication( page );
			}
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
