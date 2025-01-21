/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */

import { getMerchant, getShopper } from '../../utils/helpers';
import * as merchant from '../../utils/merchant';
import * as shopper from '../../utils/shopper';
import * as devtools from '../../utils/devtools';

const cardTestingProtectionStates = [ false, true ];
const bnplProviders = [ 'Affirm', 'Afterpay' ];

test.describe( 'BNPL checkout', () => {
	let merchantPage: Page;
	let shopperPage: Page;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;

		await merchant.enablePaymentMethods( merchantPage, bnplProviders );
	} );

	test.afterAll( async () => {
		await merchant.disablePaymentMethods( merchantPage, bnplProviders );
	} );

	for ( const ctpEnabled of cardTestingProtectionStates ) {
		test.describe( `Carding protection ${ ctpEnabled }`, () => {
			test.beforeAll( async () => {
				if ( ctpEnabled ) {
					await devtools.enableCardTestingProtection( merchantPage );
				}
			} );

			test.afterAll( async () => {
				if ( ctpEnabled ) {
					await devtools.disableCardTestingProtection( merchantPage );
				}
			} );

			for ( const provider of bnplProviders ) {
				test( `Checkout with ${ provider }`, async () => {
					await shopper.addCartProduct( shopperPage, 17 ); // Belt
					await shopper.setupCheckout( shopperPage );
					await shopper.selectPaymentMethod( shopperPage, provider );
					await shopper.expectFraudPreventionToken(
						shopperPage,
						ctpEnabled
					);
					await shopper.placeOrder( shopperPage );
					await shopperPage
						.getByText( 'Authorize Test Payment' )
						.click();
					await expect(
						shopperPage.getByRole( 'heading', {
							name: 'Order received',
						} )
					).toBeVisible();
				} );
			}
		} );
	}
} );
