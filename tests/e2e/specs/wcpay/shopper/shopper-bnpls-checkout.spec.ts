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
import * as navigation from '../../../utils/shopper-navigation';
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const cardTestingProtectionStates = [ false, true ];
const bnplProviders = [ 'Affirm', 'Cash App Afterpay' ];

// using multiple products to prevent the "order duplication service" to be triggered.
const products = [ 'belt', 'sunglasses' ];

const checkoutWithBnpl = async (
	page: Page,
	provider: string,
	productSlug: string,
	ctpEnabled: boolean
): Promise< string > => {
	await navigation.goToProductPageBySlug( page, productSlug );

	await page.locator( '.single_add_to_cart_button' ).click();
	await page.waitForLoadState( 'domcontentloaded' );
	await expect(
		page.getByText( /has been added to your cart\./ )
	).toBeVisible();

	await shopper.setupCheckout( page );
	await shopper.selectPaymentMethod( page, provider );
	await shopper.expectFraudPreventionToken( page, ctpEnabled );
	await shopper.placeOrder( page );
	await expect( page.getByText( /test payment page/ ) ).toBeVisible();
	// sometimes it's a button, other times it's a link.
	await page.getByText( 'Authorize Test Payment' ).click();
	await expect(
		page.getByRole( 'heading', { name: 'Order received' } )
	).toBeVisible();

	const orderId = page.url().match( /\/order-received\/(\d+)\// )?.[ 1 ];
	if ( ! orderId ) {
		throw new Error(
			`Expected an order-received URL with an order ID, got: ${ page.url() }`
		);
	}
	return orderId;
};
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
					await checkoutWithBnpl(
						shopperPage,
						provider,
						products[ i % products.length ],
						ctpEnabled
					);
				} );
			}
		} );
	}

	for ( let i = 0; i < bnplProviders.length; i++ ) {
		const provider = bnplProviders[ i ];

		test( `merchant can see and refund a ${ provider } order`, async () => {
			const orderId = await checkoutWithBnpl(
				shopperPage,
				provider,
				products[ i % products.length ],
				false
			);

			await verifyOrderAndRefund( merchantPage, orderId );
		} );
	}
} );
