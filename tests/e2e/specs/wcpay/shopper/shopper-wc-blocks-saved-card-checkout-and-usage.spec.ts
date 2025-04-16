/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	describeif,
	ensureCustomerIsLoggedIn,
	getShopper,
} from '../../../utils/helpers';
import { shouldRunWCBlocksTests } from '../../../utils/constants';
import {
	goToMyAccount,
	goToCheckoutWCB,
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
import { config } from '../../../config/default';

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Saved cards',
	{ tag: '@critical' },
	() => {
		let shopperPage: Page;
		const card = config.cards.basic;

		test.beforeAll( async ( { browser }, { project } ) => {
			shopperPage = (
				await getShopper( browser, true, project.use.baseURL )
			 ).shopperPage;

			await ensureCustomerIsLoggedIn( shopperPage, project );
		} );

		test( 'should be able to save basic card on Blocks checkout', async () => {
			await emptyCart( shopperPage );
			await addToCartFromShopPage( shopperPage, config.products.belt );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, card );
			await setSavePaymentMethod( shopperPage, true );
			await placeOrderWCB( shopperPage );

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
		} );

		test( 'should delete the card ', async () => {
			await goToMyAccount( shopperPage, 'payment-methods' );
			await deleteSavedCard( shopperPage, card );
			await expect(
				shopperPage.getByText( 'Payment method deleted.' )
			).toBeVisible();
		} );
	}
);
