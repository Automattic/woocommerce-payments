/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */

import { config } from '../../../config/default';
import * as shopper from '../../../utils/shopper';
import * as devtools from '../../../utils/devtools';
import { getMerchant, getShopper } from '../../../utils/helpers';
import { goToOrder } from '../../../utils/merchant-navigation';

test.describe( 'Successful purchase', () => {
	let merchantPage: Page;
	let shopperPage: Page;

	for ( const ctpEnabled of [ false, true ] ) {
		test.describe( `Carding protection ${ ctpEnabled }`, () => {
			test.beforeAll( async ( { browser } ) => {
				shopperPage = ( await getShopper( browser ) ).shopperPage;
				merchantPage = ( await getMerchant( browser ) ).merchantPage;
				if ( ctpEnabled ) {
					await devtools.enableCardTestingProtection( merchantPage );
				}
			} );

			test.afterAll( async () => {
				if ( ctpEnabled ) {
					await devtools.disableCardTestingProtection( merchantPage );
				}
			} );

			test.beforeEach( async () => {
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

				// When CTP is disabled, verify the order in WC admin.
				if ( ! ctpEnabled ) {
					const orderId = await shopperPage
						.locator(
							'.woocommerce-order-overview__order.order > strong'
						)
						.innerText();

					await goToOrder( merchantPage, orderId );
					await expect(
						merchantPage.locator( '#order_status' )
					).toHaveValue( /wc-(processing|completed)/ );
				}
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
