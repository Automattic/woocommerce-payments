/**
 * External dependencies
 */
import type { BrowserContext, Page } from '@playwright/test';
import { test, expect, getAuthState } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import * as navigation from '../../../utils/shopper-navigation';
import * as shopper from '../../../utils/shopper';
import * as merchant from '../../../utils/merchant';
import * as devtools from '../../../utils/devtools';
import { verifyOrderAndRefund } from '../../../utils/merchant-orders';

const cardTestingProtectionStates = [ false, true ];
const bnplProviders = [ 'Affirm', 'Cash App Afterpay' ];

// Use different products per provider to avoid the order duplication protection.
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

test.describe( 'BNPL checkout', { tag: [ '@shopper', '@critical' ] }, () => {
	let merchantContext: BrowserContext;
	let merchantPage: Page;
	let shopperContext: BrowserContext;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();

		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();

		wasMulticurrencyEnabled = await merchant.isMulticurrencyEnabled(
			merchantPage
		);
		await merchant.enablePaymentMethods( merchantPage, bnplProviders );
		if ( wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
	} );

	test.afterAll( async () => {
		if ( merchantPage ) {
			await merchant.disablePaymentMethods( merchantPage, bnplProviders );
			if ( wasMulticurrencyEnabled ) {
				await merchant.activateMulticurrency( merchantPage );
			}
		}

		await merchantContext?.close().catch( () => undefined );
		await shopperContext?.close().catch( () => undefined );
	} );

	test.beforeEach( async () => {
		await shopper.emptyCart( shopperPage );
	} );

	for ( const ctpEnabled of cardTestingProtectionStates ) {
		test.describe( `Carding protection ${ ctpEnabled }`, () => {
			test.beforeAll( async () => {
				if ( ctpEnabled ) {
					await devtools.enableCardTestingProtection();
				} else {
					await devtools.disableCardTestingProtection();
				}
			} );

			test.afterAll( async () => {
				if ( ctpEnabled ) {
					await devtools.disableCardTestingProtection();
				}
			} );

			for ( const [ index, provider ] of bnplProviders.entries() ) {
				test( `Checkout with ${ provider }`, async () => {
					await checkoutWithBnpl(
						shopperPage,
						provider,
						products[ index % products.length ],
						ctpEnabled
					);
				} );
			}
		} );
	}

	for ( const [ index, provider ] of bnplProviders.entries() ) {
		test( `merchant can see and refund a ${ provider } order`, async () => {
			const orderId = await checkoutWithBnpl(
				shopperPage,
				provider,
				products[ index % products.length ],
				false
			);

			await verifyOrderAndRefund( merchantPage, orderId );
		} );
	}
} );
