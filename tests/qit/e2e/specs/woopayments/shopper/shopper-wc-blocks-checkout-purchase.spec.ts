/**
 * External dependencies
 */
import { test, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';
import * as devtools from '../../../utils/devtools';
import {
	addToCartFromShopPage,
	confirmCardAuthenticationWCB,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	expectFraudPreventionToken,
	waitForOrderConfirmationWCB,
	placeOrderWCB,
	emptyCart,
} from '../../../utils/shopper';

test.describe(
	'WooCommerce Blocks > Successful purchase',
	{ tag: [ '@shopper', '@critical', '@blocks' ] },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();
			await devtools.disableCardTestingProtection();
			await devtools.disableFailedTransactionRateLimiter();
		} );

		test.afterAll( async () => {
			await shopperContext?.close();
		} );

		test.beforeEach( async () => {
			await emptyCart( shopperPage );
		} );

		test( 'using a basic card', async () => {
			await addToCartFromShopPage( shopperPage, config.products.belt );
			await goToCheckoutWCB( shopperPage );
			await expectFraudPreventionToken( shopperPage, false );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, config.cards.basic );
			await placeOrderWCB( shopperPage );
			await waitForOrderConfirmationWCB( shopperPage );
		} );

		test( 'using a 3DS card', async () => {
			await addToCartFromShopPage(
				shopperPage,
				config.products.sunglasses
			);
			await goToCheckoutWCB( shopperPage );
			await expectFraudPreventionToken( shopperPage, false );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, config.cards[ '3ds' ] );
			await placeOrderWCB( shopperPage, false );
			await confirmCardAuthenticationWCB( shopperPage );
			await waitForOrderConfirmationWCB( shopperPage );
		} );
	}
);
