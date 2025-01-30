/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	checkPageExists,
	describeif,
	getMerchant,
	getShopper,
} from '../../utils/helpers';
import { shouldRunWCBlocksTests } from '../../utils/constants';
import {
	goToMyAccount,
	goToShop,
	goToCheckoutWCB,
} from '../../utils/shopper-navigation';
import {
	addCartProduct,
	deleteSavedCard,
	emptyCart,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	selectSavedCardOnCheckout,
} from '../../utils/shopper';
import { addWCBCheckoutPage } from '../../utils/merchant';
import { config } from '../../config/default';

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Saved cards',
	() => {
		let shopperPage: Page;
		const card = config.cards.basic;

		test.beforeAll( async ( { browser }, { project } ) => {
			shopperPage = ( await getShopper( browser ) ).shopperPage;
			if (
				! ( await checkPageExists(
					shopperPage,
					project.use.baseURL + '/checkout-wcb'
				) )
			) {
				const { merchantPage } = await getMerchant( browser );
				await addWCBCheckoutPage( merchantPage );
			}
		} );

		test( 'should be able to save basic card on Blocks checkout', async () => {
			await emptyCart( shopperPage );
			await goToShop( shopperPage );
			await addCartProduct( shopperPage );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await fillCardDetailsWCB( shopperPage, config.cards.basic );
			await shopperPage
				.getByLabel(
					'Save payment information to my account for future purchases.'
				)
				.click();
			await shopperPage
				.getByRole( 'button', { name: 'Place Order' } )
				.click();
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
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
			await goToShop( shopperPage );
			await addCartProduct( shopperPage );
			await goToCheckoutWCB( shopperPage );
			await fillBillingAddressWCB(
				shopperPage,
				config.addresses.customer.billing
			);
			await selectSavedCardOnCheckout( shopperPage, card );
			await shopperPage
				.getByRole( 'button', { name: 'Place Order' } )
				.click();
			await expect(
				shopperPage.getByRole( 'heading', {
					name: 'Order received',
				} )
			).toBeVisible();
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
