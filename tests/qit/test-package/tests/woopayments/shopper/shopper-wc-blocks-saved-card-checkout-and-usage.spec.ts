/**
 * External dependencies
 */
import { test, expect, getAuthState } from '../../../fixtures/auth';
import type { BrowserContext, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import {
	goToCheckoutWCB,
	goToMyAccount,
} from '../../../utils/shopper-navigation';
import {
	addToCartFromShopPage,
	deleteSavedCard,
	emptyCart,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	placeOrderWCB,
	selectSavedCardOnCheckout,
	setSavePaymentMethod,
} from '../../../utils/shopper';

test.describe(
	'WooCommerce Blocks > Saved cards',
	{ tag: [ '@shopper', '@critical', '@blocks' ] },
	() => {
		let shopperContext: BrowserContext;
		let shopperPage: Page;
		const card = config.cards.basic;

		test.beforeAll( async ( { browser } ) => {
			shopperContext = await browser.newContext( {
				storageState: await getAuthState( browser, 'customer' ),
			} );
			shopperPage = await shopperContext.newPage();
		} );

		test.afterAll( async () => {
			await emptyCart( shopperPage );
			await shopperContext?.close();
		} );

		test.beforeEach( async () => {
			await emptyCart( shopperPage );
		} );

		test( 'should be able to save basic card on Blocks checkout', async () => {
			await addToCartFromShopPage( shopperPage, config.products.belt );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, card );
			await setSavePaymentMethod( shopperPage, true );
			await placeOrderWCB( shopperPage );

			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();

			await goToMyAccount( shopperPage, 'payment-methods' );
			await expect(
				shopperPage.getByText( card.label ).first()
			).toBeVisible();
			await expect(
				shopperPage
					.getByText(
						`${ card.expires.month }/${ card.expires.year }`
					)
					.first()
			).toBeVisible();
		} );

		test( 'should process a payment with the saved card from Blocks checkout', async () => {
			await addToCartFromShopPage( shopperPage, config.products.cap );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await selectSavedCardOnCheckout( shopperPage, card );
			await placeOrderWCB( shopperPage );
			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible();
		} );

		test( 'should delete the card', async () => {
			await goToMyAccount( shopperPage, 'payment-methods' );
			await deleteSavedCard( shopperPage, card );
			await expect(
				shopperPage.getByText( 'Payment method deleted.' )
			).toBeVisible();
		} );
	}
);
