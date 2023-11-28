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
			await shopper.goToShop();
			await page.click( 'li.product > a > img[src*="beanie"' );
		},
		'product/beanie',
	],
	[
		'cart page',
		async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage(
				config.get( 'products.simple.name' )
			);
			await shopper.goToCart();
		},
		'cart',
	],
	[
		'checkout page',
		async () => {
			await shopper.goToShop();
			await shopper.addToCartFromShopPage(
				config.get( 'products.simple.name' )
			);
			await shopper.goToCheckout();
		},
		'checkout',
	],
];

describe( 'Shopper Multi-Currency', () => {
	it( "should display currency switcher if it's enabled", async () => {
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await merchantWCP.activateMulticurrency();
		await shopper.goToShop();
		await page.waitForSelector( 'select[name=currency]', {
			visible: true,
		} );
	} );

	it( "should not display currency switcher if it's disabled", async () => {
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await merchantWCP.deactivateMulticurrency();
		await shopper.goToShop();

		const currencySwitcher = await page.$( 'select[name=currency]' );
		expect( currencySwitcher ).toBeNull();

		// Activate it again for the other tests.
		await merchantWCP.activateMulticurrency();
	} );

	describe.each( pagesTable )(
		'Should allow merchant to switch currency',
		( pageName, setupTest, url ) => {
			it( `at the ${ pageName }`, async () => {
				// expect( 1 ).toBe( 1 );
				await setupTest();
				await page.waitForSelector( 'select[name=currency]', {
					visible: true,
				} );
				await page.select( 'select[name=currency]', 'EUR' );
				await expect( page.url() ).toContain(
					`${ url }/?currency=EUR`
				);
				await page.waitForSelector(
					'select[name=currency] option[value=EUR][selected]'
				);
				// Change it back to USD for the other tests.
				await page.select( 'select[name=currency]', 'USD' );
			} );
		}
	);
} );
