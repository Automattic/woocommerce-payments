/**
 * Migrated from tests/e2e/specs/wcpay/shopper/shopper-wc-blocks-saved-card-checkout-and-usage.spec.ts
 */
/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	cards,
	defaultBillingAddress,
	addToCartFromShopPage,
	setupCheckout,
	fillCardDetails,
	placeOrder,
	setSavePaymentMethod,
	selectSavedCardOnCheckout,
	deleteSavedCard,
	emptyCart,
	CHECKOUT_URLS,
} from '../../../helpers/checkout.js';

const CUSTOMER_USERNAME = process.env.QIT_CUSTOMER_USERNAME || 'testcustomer';
const CUSTOMER_PASSWORD = process.env.QIT_CUSTOMER_PASSWORD || 'testpass123';

const loginAsCustomer = async ( page ) => {
	await page.goto( '/my-account' );
	await page.waitForLoadState( 'domcontentloaded' );

	const logoutLink = page.locator(
		'.woocommerce-MyAccount-navigation-link--customer-logout'
	);
	if ( ( await logoutLink.count() ) > 0 ) {
		return;
	}

	const usernameInput = page.locator( 'input#username' );
	const passwordInput = page.locator( 'input#password' );

	await expect( usernameInput ).toBeVisible();
	await usernameInput.fill( CUSTOMER_USERNAME );
	await passwordInput.fill( CUSTOMER_PASSWORD );
	await page.locator( 'button[name="login"]' ).click();

	await expect( logoutLink ).toBeVisible();
};

const goToPaymentMethods = async ( page ) => {
	await page.goto( '/my-account/payment-methods/' );
	await page.waitForLoadState( 'domcontentloaded' );
	await expect(
		page.getByRole( 'heading', { name: /Payment methods/i } )
	).toBeVisible( { timeout: 15000 } );
};

const ensureCartHasItem = async ( page ) => {
	await page.goto( '/cart' );
	await page.waitForLoadState( 'domcontentloaded' );

	const cartEmptyMessage = page.locator(
		'.cart-empty, .wc-empty-cart-message, .wc-block-components-notice-banner__content'
	);
	if (
		( await cartEmptyMessage.count() ) > 0 &&
		( await cartEmptyMessage.first().isVisible() )
	) {
		return false;
	}

	const cartItems = page.locator(
		'.shop_table .cart_item, .wc-block-cart-items-list .wc-block-cart-items-list-item'
	);
	return ( await cartItems.count() ) > 0;
};

const prepareBlocksCheckout = async ( page ) => {
	let cartReady = false;

	for ( let attempt = 0; attempt < 2 && ! cartReady; attempt++ ) {
		await addToCartFromShopPage( page );
		await page.waitForTimeout( 1000 );
		cartReady = await ensureCartHasItem( page );
	}

	if ( ! cartReady ) {
		throw new Error( 'Unable to prepare cart for Blocks checkout.' );
	}

	await setupCheckout( page, defaultBillingAddress, CHECKOUT_URLS.blocks );
};

const assertSavedCardVisible = async ( page, card ) => {
	const lastFour = card.number.slice( -4 );
	await expect(
		page.getByText( new RegExp( `ending in\\s*${ lastFour }`, 'i' ) )
	).toBeVisible( { timeout: 15000 } );
	await expect(
		page.getByText(
			new RegExp( `${ card.expires.month }\/${ card.expires.year }` )
		)
	).toBeVisible( { timeout: 15000 } );
};

const removeSavedCardIfPresent = async ( page, card ) => {
	await goToPaymentMethods( page );

	const lastFour = card.number.slice( -4 );
	const savedCardRow = page
		.locator( 'table tr, ul li, div' )
		.filter( {
			hasText: new RegExp( `ending in\\s*${ lastFour }`, 'i' ),
		} )
		.first();

	if ( ( await savedCardRow.count() ) === 0 ) {
		return;
	}

	await deleteSavedCard( page, card );
	await expect(
		page.getByText( /Payment method deleted\.?/i )
	).toBeVisible( { timeout: 15000 } );
};

test.describe.serial(
	'WooCommerce Blocks > Saved cards @blocks @shopper @critical @shopper-wc-blocks-saved-card-checkout-and-usage',
	() => {
		let shopperPage;

		test.beforeAll( async ( { browser } ) => {
			shopperPage = await browser.newPage();
			await loginAsCustomer( shopperPage );
			await emptyCart( shopperPage ).catch( () => {} );
			await removeSavedCardIfPresent(
				shopperPage,
				cards.basic
			).catch( () => {} );
		} );

		test.afterAll( async () => {
			await emptyCart( shopperPage ).catch( () => {} );
			await removeSavedCardIfPresent(
				shopperPage,
				cards.basic
			).catch( () => {} );
			await shopperPage?.close();
		} );

		test( 'saves the basic card on Blocks checkout', async () => {
			await prepareBlocksCheckout( shopperPage );
			await fillCardDetails( shopperPage, cards.basic );
			await setSavePaymentMethod( shopperPage, true );
			await placeOrder( shopperPage );

			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible( { timeout: 20000 } );

			await goToPaymentMethods( shopperPage );
			await assertSavedCardVisible( shopperPage, cards.basic );
		} );

		test( 'processes a payment with the saved card on Blocks checkout', async () => {
			await prepareBlocksCheckout( shopperPage );
			await selectSavedCardOnCheckout( shopperPage, cards.basic );
			await placeOrder( shopperPage );

			await expect(
				shopperPage.getByRole( 'heading', { name: 'Order received' } )
			).toBeVisible( { timeout: 20000 } );
		} );

		test( 'deletes the saved card from My Account', async () => {
			await goToPaymentMethods( shopperPage );
			await deleteSavedCard( shopperPage, cards.basic );
			await expect(
				shopperPage.getByText( /Payment method deleted\.?/i )
			).toBeVisible( { timeout: 15000 } );
		} );
	}
);
