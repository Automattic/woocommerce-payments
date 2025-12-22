/**
 * External dependencies
 */
import type { BrowserContext, Page } from '@playwright/test';
import { test, expect, getAuthState } from '../../../fixtures/auth';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import * as merchant from '../../../utils/merchant';
import * as shopper from '../../../utils/shopper';
import { goToProductPageBySlug } from '../../../utils/shopper-navigation';

test.describe( 'Klarna Checkout', { tag: '@shopper' }, () => {
	let merchantContext: BrowserContext;
	let shopperContext: BrowserContext;
	let merchantPage: Page;
	let shopperPage: Page;
	let wasMulticurrencyEnabled = false;

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
		if ( wasMulticurrencyEnabled ) {
			await merchant.deactivateMulticurrency( merchantPage );
		}
		await merchant.enablePaymentMethods( merchantPage, [ 'klarna' ] );
	} );

	test.afterAll( async () => {
		if ( shopperPage ) {
			await shopper.emptyCart( shopperPage );
		}
		if ( merchantPage ) {
			await merchant.disablePaymentMethods( merchantPage, [ 'klarna' ] );
			if ( wasMulticurrencyEnabled ) {
				await merchant.activateMulticurrency( merchantPage );
			}
		}
		await merchantContext?.close();
		await shopperContext?.close();
	} );

	test.beforeEach( async () => {
		await shopper.emptyCart( shopperPage );
	} );

	test( 'shows the message in the product page', async () => {
		await goToProductPageBySlug( shopperPage, 'belt' );

		// Since we can't control the exact contents of the iframe, we just make sure it's there.
		await expect(
			shopperPage
				.frameLocator( '#payment-method-message iframe' )
				.locator( 'body' )
		).not.toBeEmpty();
	} );

	test(
		'allows to use Klarna as a payment method',
		{ tag: '@critical' },
		async () => {
			const klarnaBillingAddress = {
				...config.addresses.customer.billing,
				email: 'customer@email.us',
				phone: '+13106683312',
				firstname: 'Test',
				lastname: 'Person-us',
			};

			await shopper.setupProductCheckout(
				shopperPage,
				[ [ config.products.belt, 1 ] ],
				klarnaBillingAddress
			);
			await shopper.selectPaymentMethod( shopperPage, 'Klarna' );
			await shopper.placeOrder( shopperPage );

			// Since we don't control the HTML in the Klarna playground page,
			// verifying the redirect is all we can do consistently.
			await expect( shopperPage ).toHaveURL( /.*klarna\.com/ );
		}
	);
} );
