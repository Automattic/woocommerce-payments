/**
 * External dependencies
 */
import test, { Page, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import { config } from '../../../config/default';
import { goToCart, goToMyAccount } from '../../../utils/shopper-navigation';
import {
	ensureCustomerIsLoggedIn,
	getShopper,
	isUIUnblocked,
} from '../../../utils/helpers';
import {
	addSavedCard,
	addToCartFromShopPage,
	deleteSavedCard,
	emptyCart,
} from '../../../utils/shopper';

// Enough of the simple product to push the cart total past Stripe's
// USD amount limit ($999,999.99).
const largeCartQuantity = '100000';
const savedCard = config.cards.basic;

test.describe( 'Add payment method with a cart over the amount limit', () => {
	let shopperPage: Page;

	test.beforeAll( async ( { browser }, { project } ) => {
		shopperPage = ( await getShopper( browser, true, project.use.baseURL ) )
			.shopperPage;
		await ensureCustomerIsLoggedIn( shopperPage, project );

		await emptyCart( shopperPage );
		await addToCartFromShopPage( shopperPage, config.products.simple );
		await goToCart( shopperPage );
		await shopperPage
			.locator( 'input.qty' )
			.first()
			.fill( largeCartQuantity );
		await shopperPage
			.getByRole( 'button', { name: 'Update cart' } )
			.click();
		await isUIUnblocked( shopperPage );
		await expect( shopperPage.locator( 'input.qty' ).first() ).toHaveValue(
			'100000'
		);
	} );

	test.afterAll( async () => {
		await emptyCart( shopperPage );

		await goToMyAccount( shopperPage, 'payment-methods' );
		const savedCardRow = shopperPage
			.getByRole( 'row', { name: savedCard.label } )
			.first();
		if ( await savedCardRow.isVisible().catch( () => false ) ) {
			await deleteSavedCard( shopperPage, savedCard );
		}
	} );

	test( 'loads the card form', async () => {
		await goToMyAccount( shopperPage, 'add-payment-method' );
		await isUIUnblocked( shopperPage );
		await shopperPage.getByText( 'Card', { exact: true } ).click();

		await expect(
			shopperPage
				.frameLocator( 'iframe[title="Secure payment input frame"]' )
				.getByPlaceholder( '1234 1234 1234 1234' )
		).toBeVisible();
		await expect(
			shopperPage.locator( '.wcpay-upe-element .woocommerce-error' )
		).toHaveCount( 0 );
	} );

	test( 'saves the card', async () => {
		await goToMyAccount( shopperPage, 'payment-methods' );
		await addSavedCard( shopperPage, savedCard, 'US', '94110' );

		await expect(
			shopperPage.getByText( 'Payment method successfully added.' )
		).toBeVisible();
		await expect(
			shopperPage.getByRole( 'row', { name: savedCard.label } ).first()
		).toBeVisible();
	} );
} );
