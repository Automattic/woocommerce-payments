/**
 * External dependencies
 */
import { test, expect, BrowserContext, Page } from '@playwright/test';
import qit from '/qitHelpers';

/**
 * Internal dependencies
 */
import { describeif } from '../../../utils/helpers';
import { config } from '../../../config/default';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';
import {
	addToCartFromShopPage,
	confirmCardAuthenticationWCB,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	placeOrderWCB,
} from '../../../utils/shopper';

const shouldRunWCBlocksTests = process.env.SKIP_WC_BLOCKS_TESTS !== '1';

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Successful purchase',
	{ tag: [ '@critical', '@blocks' ] },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;

		test.beforeAll( async ( { browser } ) => {
			shopperContext = await browser.newContext();
			shopperPage = await shopperContext.newPage();
			const { username, password } = config.users.customer;
			await qit.loginAs( shopperPage, username, password );
		} );

		test.afterAll( async () => {
			await shopperContext?.close();
		} );

		test( 'using a basic card', async () => {
			await addToCartFromShopPage( shopperPage, config.products.belt );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, config.cards.basic );
			await placeOrderWCB( shopperPage );
		} );

		test( 'using a 3DS card', async () => {
			await addToCartFromShopPage(
				shopperPage,
				config.products.sunglasses
			);
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, config.cards[ '3ds' ] );
			await placeOrderWCB( shopperPage, false );
			await confirmCardAuthenticationWCB( shopperPage );
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
		} );
	}
);
