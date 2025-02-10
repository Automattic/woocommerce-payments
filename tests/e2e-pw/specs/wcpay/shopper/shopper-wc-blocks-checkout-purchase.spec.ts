/**
 * External dependencies
 */
import { test, expect, Page } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	checkPageExists,
	describeif,
	getMerchant,
	getShopper,
} from '../../../utils/helpers';
import { shouldRunWCBlocksTests } from '../../../utils/constants';
import { addWCBCheckoutPage } from '../../../utils/merchant';
import { goToCheckoutWCB } from '../../../utils/shopper-navigation';
import {
	addToCartFromShopPage,
	confirmCardAuthenticationWCB,
	fillBillingAddressWCB,
	fillCardDetailsWCB,
	placeOrderWCB,
} from '../../../utils/shopper';
import { config } from '../../../config/default';

describeif( shouldRunWCBlocksTests )(
	'WooCommerce Blocks > Successful purchase',
	() => {
		let shopperPage: Page;

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
