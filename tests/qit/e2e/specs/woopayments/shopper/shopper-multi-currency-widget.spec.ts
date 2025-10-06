/**
 * External dependencies
 */
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import * as merchant from '../../../utils/merchant';
import * as navigation from '../../../utils/shopper-navigation';
import * as shopper from '../../../utils/shopper';

test.describe( 'Shopper Multi-Currency widget', { tag: '@shopper' }, () => {
	let merchantContext: BrowserContext;
	let merchantPage: Page;
	let shopperContext: BrowserContext;
	let shopperPage: Page;
	let wasMulticurrencyEnabled = false;
	let originalEnabledCurrencies: string[] = [];

	// Increase the beforeAll timeout because creating contexts and fetching
	// auth state can be slow in CI/docker. 60s should be sufficient.
	test.beforeAll( async ( { browser } ) => {
		merchantContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'admin' ),
		} );
		merchantPage = await merchantContext.newPage();

		shopperContext = await browser.newContext( {
			storageState: await getAuthState( browser, 'customer' ),
		} );
		shopperPage = await shopperContext.newPage();

		await merchant.removeMultiCurrencyWidgets();
		originalEnabledCurrencies = await merchant.getEnabledCurrenciesSnapshot(
			merchantPage
		);
		wasMulticurrencyEnabled = await merchant.activateMulticurrency(
			merchantPage
		);
		await merchant.addCurrency( merchantPage, 'EUR' );
		await merchant.addMulticurrencyWidget( merchantPage );
	}, 60000 );

	test.afterAll( async () => {
		await merchant.removeMultiCurrencyWidgets();
		await merchant.restoreCurrencies(
			merchantPage,
			originalEnabledCurrencies
		);
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
		await merchantContext?.close();
		await shopperContext?.close();
	} );

	test( 'should display currency switcher widget if multi-currency is enabled', async () => {
		await navigation.goToShop( shopperPage );
		await expect(
			shopperPage.locator( '.widget select[name="currency"]' )
		).toBeVisible();
	} );

	test.describe( 'Should allow shopper to switch currency', () => {
		test.afterEach( async () => {
			await shopperPage.selectOption(
				'.widget select[name="currency"]',
				'EUR'
			);
			await expect( shopperPage ).toHaveURL( /.*currency=EUR/ );
			await navigation.goToShop( shopperPage, { currency: 'USD' } );
		} );

		test( 'at the product page', async () => {
			await navigation.goToProductPageBySlug( shopperPage, 'beanie' );
		} );

		test( 'at the cart page', async () => {
			await navigation.goToCart( shopperPage );
		} );

		test( 'at the checkout page', async () => {
			await navigation.goToCheckout( shopperPage );
		} );
	} );

	test.describe( 'Should not affect prices', () => {
		let orderId: string | null = null;
		let orderPrice: string | null = null;

		test.afterEach( async () => {
			if ( orderPrice ) {
				await expect(
					shopperPage.getByText( `${ orderPrice } USD` ).first()
				).toBeVisible();
			}
			await navigation.goToShop( shopperPage, { currency: 'USD' } );
		} );

		test( 'at the order received page', { tag: '@critical' }, async () => {
			orderId = await shopper.placeOrderWithCurrency(
				shopperPage,
				'USD'
			);
			orderPrice = await shopperPage
				.getByRole( 'row', { name: 'Total: $' } )
				.locator( '.amount' )
				.nth( 1 )
				.textContent();
		} );

		test( 'at My account > Orders', async () => {
			expect( orderId ).toBeTruthy();
			if ( ! orderId ) {
				return;
			}
			await navigation.goToOrders( shopperPage );
			await expect(
				shopperPage
					.locator( '.woocommerce-orders-table__cell-order-number' )
					.getByRole( 'link', { name: orderId } )
			).toBeVisible();
		} );
	} );

	test( 'should not display currency switcher on pay for order page', async () => {
		const orderId = await merchant.createPendingOrder();

		await merchantPage.goto(
			`/wp-admin/post.php?post=${ orderId }&action=edit`,
			{ waitUntil: 'load' }
		);
		const paymentLink = merchantPage.getByRole( 'link', {
			name: 'Customer payment page',
		} );
		const opensNewTab =
			( await paymentLink.getAttribute( 'target' ) ) === '_blank';
		let paymentPage: Page | null = null;
		if ( opensNewTab ) {
			[ paymentPage ] = await Promise.all( [
				merchantContext.waitForEvent( 'page' ),
				paymentLink.click(),
			] );
		} else {
			await paymentLink.click();
		}
		const paymentView = paymentPage ?? merchantPage;
		await paymentView.waitForLoadState( 'load' );
		await expect(
			paymentView.locator( '.widget select[name="currency"]' )
		).not.toBeVisible();
		await paymentPage?.close();
	} );

	test( 'should not display currency switcher widget if multi-currency is disabled', async () => {
		await merchant.deactivateMulticurrency( merchantPage );
		await navigation.goToShop( shopperPage );
		await expect(
			shopperPage.locator( '.widget select[name="currency"]' )
		).not.toBeVisible();
		await merchant.activateMulticurrency( merchantPage );
	} );
} );
