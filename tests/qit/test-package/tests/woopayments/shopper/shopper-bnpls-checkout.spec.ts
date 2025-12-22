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

const cardTestingProtectionStates = [ false, true ];
const bnplProviders = [ 'Affirm', 'Cash App Afterpay' ];

// Use different products per provider to avoid the order duplication protection.
const products = [ 'belt', 'sunglasses' ];

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
					await navigation.goToProductPageBySlug(
						shopperPage,
						products[ index % products.length ]
					);

					await shopperPage
						.locator( '.single_add_to_cart_button' )
						.click();
					await shopperPage.waitForLoadState( 'domcontentloaded' );
					await expect(
						shopperPage.getByText( /has been added to your cart\./ )
					).toBeVisible();

					await shopper.setupCheckout( shopperPage );
					await shopper.selectPaymentMethod( shopperPage, provider );
					await shopper.expectFraudPreventionToken(
						shopperPage,
						ctpEnabled
					);
					await shopper.placeOrder( shopperPage );
					await expect(
						shopperPage.getByText( /test payment page/ )
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
