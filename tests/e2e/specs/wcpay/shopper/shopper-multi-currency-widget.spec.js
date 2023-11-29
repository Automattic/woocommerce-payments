/**
 * External dependencies
 */
const { merchant, shopper } = require( '@woocommerce/e2e-utils' );

/**
 * Internal dependencies
 */
import config from 'config';
import { merchantWCP, uiLoaded } from '../../../utils';

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
	it( 'should display currency switcher widget if multi-currency is enabled', async () => {
		await merchant.login();
		await merchantWCP.openWCPSettings();
		await merchantWCP.activateMulticurrency();
		await page.goto( `${ config.get( 'url' ) }wp-admin/widgets.php`, {
			waitUntil: 'networkidle0',
		} );
		await uiLoaded();

		const closeWelcomeModal = await page.$( 'button[aria-label="Close"]' );
		if ( closeWelcomeModal ) {
			await closeWelcomeModal.click();
		}

		const isWidgetAdded = await page.$(
			'.wp-block iframe[srcdoc*=\'name="currency"\']'
		);
		if ( ! isWidgetAdded ) {
			await page.click( 'button[aria-label="Add block"]' );

			const searchInput = await page.waitForSelector(
				'input.components-search-control__input'
			);
			searchInput.type( 'switcher', { delay: 20 } );

			await page.click( 'button.components-button[role="option"]' );
			await page.waitForSelector(
				'.edit-widgets-header .edit-widgets-header__actions button.is-primary'
			);
			await page.click(
				'.edit-widgets-header .edit-widgets-header__actions button.is-primary'
			);
			await expect( page ).toMatchElement( '.components-snackbar', {
				text: 'Widgets saved.',
				timeout: 15000,
			} );
		}

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
