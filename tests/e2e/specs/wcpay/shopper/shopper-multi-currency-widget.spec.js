/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, shopperWCP } from '../../../utils';
import { fillCardDetails, setupProductCheckout } from '../../../utils/payments';

const pagesTable = [
	[
		'home page',
		async () => {
			await shopper.goToShop();
		},
		'shop',
	],
	[
		'product page',
		async () => {
			await page.click( 'li.product > a > img[src*="beanie"' );
		},
		'product/beanie',
	],
	[
		'cart',
		async () => {
			await shopperWCP.addToCartBySlug( 'beanie' );
			await shopper.goToCart();
		},
		'cart',
	],
	[
		'checkout',
		async () => {
			await setupProductCheckout(
				config.get( 'addresses.customer.billing' )
			);
		},
		'checkout',
	],
];

describe( 'Shopper Multi-Currency widget', () => {
	it( 'should display currency switcher widget if multi-currency is enabled', async () => {
		await merchant.login();
		await merchantWCP.addMulticurrencyWidget();
		await merchantWCP.addCurrency( 'EUR' );
		await shopper.goToShop();
		await page.waitForSelector( '.widget select[name=currency]', {
			visible: true,
			timeout: 5000,
		} );
	} );

	it( 'should not display currency switcher widget if multi-currency is disabled', async () => {
		await merchantWCP.openWCPSettings();
		await merchantWCP.deactivateMulticurrency();
		await shopper.goToShop();

		const currencySwitcher = await page.$(
			'.widget select[name=currency]'
		);
		expect( currencySwitcher ).toBeNull();

		// Activate it again for the other tests.
		await merchantWCP.activateMulticurrency();
		await merchant.logout();
	} );

	describe.each( pagesTable )(
		'Should allow shopper to switch currency',
		( pageName, setupTest, url ) => {
			it( `at the ${ pageName }`, async () => {
				await setupTest();
				await page.waitForSelector( '.widget select[name=currency]', {
					visible: true,
				} );
				await page.select( '.widget select[name=currency]', 'EUR' );
				await expect( page.url() ).toContain(
					`${ url }/?currency=EUR`
				);
				await page.waitForSelector(
					'.widget select[name=currency] option[value=EUR][selected]'
				);
				// Change it back to USD for the other tests.
				await page.select( '.widget select[name=currency]', 'USD' );
			} );
		}
	);

	it( 'should not affect prices when currency switching on My account > Orders', async () => {
		await shopper.login();
		await page.select( '.widget select[name=currency]', 'USD' );
		await setupProductCheckout(
			config.get( 'addresses.customer.billing' )
		);
		await fillCardDetails( page, config.get( 'cards.basic' ) );
		await shopper.placeOrder();
		await expect( page ).toMatch( 'Order received' );

		const orderId = await page.evaluate(
			() => document.querySelector( 'li.order strong' ).innerText
		);
		const orderTotal = Number(
			await page.evaluate( () =>
				document
					.querySelector( 'li.total strong' )
					.innerText.replace( /[^\d.]/g, '' )
			)
		);

		// Assert prices aren't affected in the order received page as well.
		await page.select( '.widget select[name=currency]', 'EUR' );
		await page.waitForSelector(
			'.widget select[name=currency] option[value=EUR][selected]'
		);
		await expect( page ).toMatch( `${ orderId }` );
		await expect( page ).toMatch( `${ orderTotal.toFixed( 2 ) } USD` );
		await page.select( '.widget select[name=currency]', 'USD' );

		await shopperWCP.goToOrders();
		await page.select( '.widget select[name=currency]', 'EUR' );
		await page.waitForSelector(
			'.widget select[name=currency] option[value=EUR][selected]'
		);
		await expect( page ).toMatch( `#${ orderId }` );
		await expect( page ).toMatch( `${ orderTotal.toFixed( 2 ) } USD` );
		await shopper.logout();
	} );

	it( 'should not display currency switcher on pay for order page', async () => {
		await merchant.login();
		await merchantWCP.createPayForOrder();
		await page.click( '.wc-order-status a' );

		const currencySwitcher = await page.$(
			'.widget select[name=currency]'
		);
		expect( currencySwitcher ).toBeNull();
	} );
} );
