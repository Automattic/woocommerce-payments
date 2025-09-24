/**
 * Migrated from tests/e2e/specs/wcpay/shopper/shopper-checkout-save-card-and-purchase.spec.ts
 */
/**
 * External dependencies
 */
import { test, expect } from '@playwright/test';

/**
 * Internal dependencies
 */
import {
	addToCartFromShopPage,
	setupCheckout,
	fillCardDetails,
	placeOrder,
	confirmCardAuthentication,
	setSavePaymentMethod,
	selectSavedCardOnCheckout,
	deleteSavedCard,
	emptyCart,
	cards,
	defaultBillingAddress,
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

const prepareCheckout = async ( page, checkoutUrl ) => {
	let cartReady = false;

	for ( let attempt = 0; attempt < 2 && ! cartReady; attempt++ ) {
		await addToCartFromShopPage( page );
		await page.waitForTimeout( 1000 );
		cartReady = await ensureCartHasItem( page );
	}

	if ( ! cartReady ) {
		throw new Error(
			'Unable to add product to cart for checkout preparation.'
		);
	}

	await setupCheckout( page, defaultBillingAddress, checkoutUrl );
};

const cardScenarios = [
	{ key: 'basic', card: cards.basic, requires3ds: false },
	{ key: '3ds', card: cards[ '3ds' ], requires3ds: true },
];

const checkoutVariants = [
	{
		key: 'shortcode',
		description: 'Classic shortcode checkout',
		url: CHECKOUT_URLS.shortcode,
	},
	{
		key: 'blocks',
		description: 'Blocks checkout',
		url: CHECKOUT_URLS.blocks,
	},
];

test.describe(
	'Shopper can save cards on checkout and pay later @critical @shopper @saved-cards',
	() => {
		checkoutVariants.forEach( ( variant ) => {
			test.describe.serial( variant.description, () => {
				let customerPage;

				test.beforeAll( async ( { browser } ) => {
					customerPage = await browser.newPage();
					await loginAsCustomer( customerPage );
					await emptyCart( customerPage ).catch( () => {} );
				} );

				test.afterAll( async () => {
					await emptyCart( customerPage ).catch( () => {} );
					await customerPage?.close();
				} );

				cardScenarios.forEach( ( scenario ) => {
					test.describe.serial( `${ scenario.key } card`, () => {
						test( 'saves the card during checkout', async () => {
							await prepareCheckout( customerPage, variant.url );
							await fillCardDetails(
								customerPage,
								scenario.card
							);
							await setSavePaymentMethod( customerPage, true );
							await placeOrder( customerPage );

							if ( scenario.requires3ds ) {
								await confirmCardAuthentication( customerPage );
							}

							await expect(
								customerPage.getByRole( 'heading', {
									name: 'Order received',
								} )
							).toBeVisible( { timeout: 20000 } );

							await goToPaymentMethods( customerPage );

							const lastFour = scenario.card.number.slice( -4 );
							await expect(
								customerPage.getByText(
									new RegExp(
										`ending in\\s*${ lastFour }`,
										'i'
									)
								)
							).toBeVisible( { timeout: 15000 } );
							await expect(
								customerPage.getByText(
									new RegExp(
										`${ scenario.card.expires.month }\/${ scenario.card.expires.year }`
									)
								)
							).toBeVisible( { timeout: 15000 } );
						} );

						test( 'processes a payment with the saved card', async () => {
							await prepareCheckout( customerPage, variant.url );
							await selectSavedCardOnCheckout(
								customerPage,
								scenario.card
							);
							await placeOrder( customerPage );

							if ( scenario.requires3ds ) {
								await confirmCardAuthentication( customerPage );
							}

							await expect(
								customerPage.getByRole( 'heading', {
									name: 'Order received',
								} )
							).toBeVisible( { timeout: 20000 } );
						} );

						test( 'deletes the saved card from My Account', async () => {
							await goToPaymentMethods( customerPage );
							await deleteSavedCard(
								customerPage,
								scenario.card
							);
							await expect(
								customerPage.getByText(
									/Payment method deleted\.?/i
								)
							).toBeVisible( { timeout: 15000 } );
						} );
					} );
				} );

				test( 'prevents guests from saving cards', async ( {
					browser,
				} ) => {
					const guestPage = await browser.newPage();

					try {
						await addToCartFromShopPage( guestPage );
						await setupCheckout(
							guestPage,
							defaultBillingAddress,
							variant.url
						);

						const saveCheckbox = guestPage.getByRole( 'checkbox', {
							name: /(save|store).*account/i,
						} );
						await expect( saveCheckbox ).toHaveCount( 0 );
						await expect(
							guestPage.locator(
								'input[name="save_payment_method"]'
							)
						).toHaveCount( 0 );
					} finally {
						await emptyCart( guestPage ).catch( () => {} );
						await guestPage.close();
					}
				} );
			} );
		} );
	}
);
