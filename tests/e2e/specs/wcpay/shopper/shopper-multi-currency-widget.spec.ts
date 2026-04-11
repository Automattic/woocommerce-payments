/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';
/**
 * Internal dependencies
 */
import * as merchant from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import * as navigation from '../../../utils/shopper-navigation';
import { goToOrder } from '../../../utils/merchant-navigation';
import RestAPI from '../../../utils/rest-api';
import { getMerchant, getShopper } from '../../../utils/helpers';

test.describe( 'Shopper Multi-Currency widget', () => {
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled: boolean;

	test.beforeAll( async ( { browser } ) => {
		shopperPage = ( await getShopper( browser ) ).shopperPage;
		merchantPage = ( await getMerchant( browser ) ).merchantPage;
		wasMulticurrencyEnabled = await merchant.activateMulticurrency(
			merchantPage
		);
		await merchant.restoreCurrencies( merchantPage );
		await merchant.addMulticurrencyWidget( merchantPage );
	} );

	test.afterAll( async ( {}, { project } ) => {
		await merchant.removeMultiCurrencyWidgets( project.use.baseURL );
		if ( ! wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
	} );

	test( 'should display currency switcher widget if multi-currency is enabled', async () => {
		await navigation.goToShop( shopperPage );
		await expect(
			shopperPage.locator( '.widget select[name=currency]' )
		).toBeVisible();
	} );

	test.describe( 'Should allow shopper to switch currency', () => {
		test.afterEach( async () => {
			await shopperPage.selectOption(
				'.widget select[name=currency]',
				'EUR'
			);
			await expect( shopperPage ).toHaveURL( /.*currency=EUR/ );

			// Change it back to USD for the other tests.
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
		let orderId: string;
		let orderPrice: string;

		test.afterEach( async () => {
			await expect(
				shopperPage.getByText( `${ orderPrice } USD` ).first()
			).toBeVisible();

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
			await navigation.goToOrders( shopperPage );
			await expect(
				shopperPage
					.locator( '.woocommerce-orders-table__cell-order-number' )
					.getByRole( 'link', { name: orderId } )
			).toBeVisible();
		} );
	} );

	test( 'should not display currency switcher on pay for order page', async ( {}, {
		project,
	} ) => {
		const restApi = new RestAPI( project.use.baseURL );
		const orderId = await restApi.createOrder();

		await goToOrder( merchantPage, orderId );
		await merchantPage
			.getByRole( 'link', { name: 'Customer payment page' } )
			.click();

		await expect(
			merchantPage.locator( '.widget select[name=currency]' )
		).not.toBeVisible();
	} );

	test( 'should not display currency switcher widget if multi-currency is disabled', async () => {
		await merchant.deactivateMulticurrency( merchantPage );

		await navigation.goToShop( shopperPage );
		await expect(
			shopperPage.locator( '.widget select[name=currency]' )
		).not.toBeVisible();

		await merchant.activateMulticurrency( merchantPage );
	} );
} );
