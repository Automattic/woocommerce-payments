/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP } from '../../../utils';

const pagesTable = [
	[ 'home page', null, 'shop' ],
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
			await shopper.addToCartFromShopPage(
				config.get( 'products.simple.name' )
			);
			await shopper.goToCart();
		},
		'cart',
	],
	[
		'checkout',
		async () => {
			await shopper.addToCartFromShopPage(
				config.get( 'products.simple.name' )
			);
			await shopper.goToCheckout();
		},
		'checkout',
	],
];

describe( 'Shopper Multi-Currency widget', () => {
	it( 'should display currency switcher if multi-currency is enabled', async () => {
		await merchant.login();
		await merchantWCP.addMulticurrencyWidget();
		await shopper.goToShop();
		await page.waitForSelector( '.widget select[name=currency]', {
			visible: true,
		} );
	} );

	it( 'should not display currency switcher widget if multi-currency is disabled', async () => {
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await merchantWCP.deactivateMulticurrency();
		await shopper.goToShop();

		const currencySwitcher = await page.$(
			'.widget select[name=currency]'
		);
		expect( currencySwitcher ).toBeNull();

		// Activate it again for the other tests.
		await merchantWCP.activateMulticurrency();
	} );

	describe.each( pagesTable )(
		'Should allow shopper to switch currency',
		( pageName, setupTest, url ) => {
			it( `at the ${ pageName }`, async () => {
				await shopper.goToShop();
				if ( typeof setupTest === 'function' ) {
					await setupTest();
				}
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
} );
