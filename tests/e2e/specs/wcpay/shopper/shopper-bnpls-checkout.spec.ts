/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */

import { getMerchant, getShopper } from '../../../utils/helpers';
import * as merchant from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import * as devtools from '../../../utils/devtools';
import { config } from '../../../config/default';

const cardTestingProtectionStates = [ false, true ];
const bnplProviders = [ 'Affirm', 'Cash App Afterpay' ];
const products = [ config.products.belt, config.products.sunglasses ];

test.describe( 'BNPL checkout', { tag: '@critical' }, () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await merchant.isMulticurrencyEnabled(
			merchantPage
		);

		await merchant.enablePaymentMethods( merchantPage, bnplProviders );
		if ( wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
	} );

	test.afterAll( async () => {
		await merchant.disablePaymentMethods( merchantPage, bnplProviders );
		if ( wasMulticurrencyEnabled ) {
			await merchant.activateMulticurrency( merchantPage );
		}
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

			for ( let i = 0; i < bnplProviders.length; i++ ) {
				const provider = bnplProviders[ i ];

				test( `Checkout with ${ provider }`, async () => {
					await shopper.addToCartFromShopPage(
						shopperPage,
						products[ i % 2 ]
					);

					await shopper.setupCheckout( shopperPage );
					await shopper.selectPaymentMethod( shopperPage, provider );
					await shopper.expectFraudPreventionToken(
						shopperPage,
						ctpEnabled
					);
					await shopper.placeOrder( shopperPage );
					await expect(
						shopperPage.getByText( 'test payment page' )
					).toBeVisible();
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
