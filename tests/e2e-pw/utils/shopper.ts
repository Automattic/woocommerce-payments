/**
 * External dependencies
 */
import { Page, expect } from 'playwright/test';
/**
 * Internal dependencies
 */
import * as navigation from './shopper-navigation';
import { config, CustomerAddress } from '../config/default';

export const isUIUnblocked = async ( page: Page ) => {
	await expect( page.locator( '.blockUI' ) ).toHaveCount( 0 );
};

export const fillBillingAddress = async (
	page: Page,
	billingAddress: CustomerAddress
) => {
	await page
		.locator( '#billing_first_name' )
		.fill( billingAddress.firstname );
	await page.locator( '#billing_last_name' ).fill( billingAddress.lastname );
	await page.locator( '#billing_company' ).fill( billingAddress.company );
	await page
		.locator( '#billing_country' )
		.selectOption( billingAddress.country );
	await page
		.locator( '#billing_address_1' )
		.fill( billingAddress.addressfirstline );
	await page
		.locator( '#billing_address_2' )
		.fill( billingAddress.addresssecondline );
	await page.locator( '#billing_city' ).fill( billingAddress.city );
	await page.locator( '#billing_state' ).selectOption( billingAddress.state );
	await page.locator( '#billing_postcode' ).fill( billingAddress.postcode );
	await page.locator( '#billing_phone' ).fill( billingAddress.phone );
	await page.locator( '#billing_email' ).fill( billingAddress.email );
};

export const placeOrder = async ( page: Page ) => {
	await page.locator( '#place_order' ).click();
};

export const addCartProduct = async (
	page: Page,
	productId = 16 // Beanie
) => {
	await page.goto( `/shop/?add-to-cart=${ productId }` );
};

export const fillCardDetails = async (
	page: Page,
	card = config.cards.basic
) => {
	if (
		await page.$(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element'
		)
	) {
		const frameHandle = await page.waitForSelector(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
		);

		const stripeFrame = await frameHandle.contentFrame();

		if ( ! stripeFrame ) return;

		await stripeFrame.locator( '[name="number"]' ).fill( card.number );

		await stripeFrame
			.locator( '[name="expiry"]' )
			.fill( card.expires.month + card.expires.year );

		await stripeFrame.locator( '[name="cvc"]' ).fill( card.cvc );

		const zip = stripeFrame.locator( '[name="postalCode"]' );

		if ( await zip.isVisible() ) {
			await zip.fill( '90210' );
		}
	} else {
		const frameHandle = await page.waitForSelector(
			'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();

		if ( ! stripeFrame ) return;

		await stripeFrame.locator( '[name="cardnumber"]' ).fill( card.number );

		await stripeFrame
			.locator( '[name="exp-date"]' )
			.fill( card.expires.month + card.expires.year );

		await stripeFrame.locator( '[name="cvc"]' ).fill( card.cvc );
	}
};

export const confirmCardAuthentication = async (
	page: Page,
	authorize = true
) => {
	// Stripe card input also uses __privateStripeFrame as a prefix, so need to make sure we wait for an iframe that
	// appears at the top of the DOM.
	await page.waitForSelector(
		'body > div > iframe[name^="__privateStripeFrame"]'
	);

	const stripeFrame = page.frameLocator(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	if ( ! stripeFrame ) return;

	const challengeFrame = stripeFrame.frameLocator(
		'iframe[name="stripe-challenge-frame"]'
	);
	if ( ! challengeFrame ) return;

	const button = challengeFrame.getByRole( 'button', {
		name: authorize ? 'Complete' : 'Fail',
	} );

	// Not ideal, but we need to wait for the loading animation to finish before we can click the button.
	await page.waitForTimeout( 1000 );

	await button.click();
};

/**
 * Retrieves the product price from the current product page.
 *
 * This function assumes that the page object has already navigated to a product page.
 */
export const getPriceFromProduct = async ( page: Page, slug: string ) => {
	await navigation.goToProductPageBySlug( page, slug );

	const priceText = await page
		.locator( 'ins .woocommerce-Price-amount.amount' )
		.first()
		.textContent();

	return priceText?.replace( /[^0-9.,]/g, '' ) ?? '';
};

/**
 * Adds a product to the cart from the shop page.
 *
 * @param {Page} page The Playwright page object.
 * @param {string|number} product The product ID or title to add to the cart.
 */
export const addToCartFromShopPage = async (
	page: Page,
	product: string | number
) => {
	if ( Number.isInteger( product ) ) {
		const addToCartSelector = `a[data-product_id="${ product }"]`;

		await page.locator( addToCartSelector ).click();
		await expect(
			page.locator( `${ addToCartSelector }.added` )
		).toBeVisible();
	} else {
		// These unicode characters are the smart (or curly) quotes: “ ”.
		const addToCartRegex = new RegExp(
			`Add to cart: \u201C${ product }\u201D`
		);

		await page.getByLabel( addToCartRegex ).click();
		await expect( page.getByLabel( addToCartRegex ) ).toHaveAttribute(
			'class',
			/added/
		);
	}
};

export const setupCheckout = async (
	page: Page,
	billingAddress: CustomerAddress
) => {
	await navigation.goToCheckout( page );
	await fillBillingAddress( page, billingAddress );
	// Woo core blocks and refreshes the UI after 1s after each key press
	// in a text field or immediately after a select field changes.
	// We need to wait to make sure that all key presses were processed by that mechanism.
	await page.waitForTimeout( 1000 );
	await isUIUnblocked( page );
	await page
		.locator( '.wc_payment_method.payment_method_woocommerce_payments' )
		.click();
};

/**
 * Sets up checkout with any number of products.
 *
 * @param {CustomerAddress} billingAddress The billing address to use for the checkout.
 * @param {Array<[string, number]>} lineItems A 2D array of line items where each line item is an array
 * that contains the product title as the first element, and the quantity as the second.
 * For example, if you want to checkout x2 "Hoodie" and x3 "Belt" then set this parameter like this:
 *
 * `[ [ "Hoodie", 2 ], [ "Belt", 3 ] ]`.
 */
export async function setupProductCheckout(
	page: Page,
	billingAddress: CustomerAddress,
	lineItems: Array< [ string, number ] > = [
		[ config.products.simple.name, 1 ],
	]
) {
	const cartSizeText = await page
		.locator( '.cart-contents .count' )
		.textContent();
	let cartSize = Number( cartSizeText?.replace( /\D/g, '' ) ?? '0' );

	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		while ( qty-- ) {
			await addToCartFromShopPage( page, productTitle );
			// Make sure the number of items in the cart is incremented before adding another item.
			await expect( page.locator( '.cart-contents .count' ) ).toHaveText(
				new RegExp( `${ ++cartSize } items?` ),
				{
					timeout: 30000,
				}
			);
		}
	}

	await setupCheckout( page, billingAddress );
}

/**
 * Places an order with a specified currency.
 *
 * @param {Page} page The Playwright page object.
 * @param {string} currency The currency code to use for the order.
 * @return {Promise<string>} The order ID.
 */
export const placeOrderWithCurrency = async (
	page: Page,
	currency: string
) => {
	await navigation.goToShopWithCurrency( page, currency );
	await setupProductCheckout( page, config.addresses.customer.billing, [
		[ config.products.simple.name, 1 ],
	] );
	await fillCardDetails( page, config.cards.basic );
	// Takes off the focus out of the Stripe elements to let Stripe logic
	// wrap up and make sure the Place Order button is clickable.
	await page.locator( '#place_order' ).focus();
	await page.waitForTimeout( 1000 );
	await placeOrder( page );
	await page.waitForURL( /\/order-received\//, { waitUntil: 'load' } );
	await expect(
		page.getByRole( 'heading', { name: 'Order received' } )
	).toBeVisible();

	const url = await page.url();
	return url.match( /\/order-received\/(\d+)\// )?.[ 1 ] ?? '';
};

export const emptyCart = async ( page: Page ) => {
	await navigation.goToCart( page );

	// Remove products if they exist.
	let products = await page.locator( '.remove' ).all();

	while ( products.length ) {
		await products[ 0 ].click();
		await isUIUnblocked( page );

		products = await page.locator( '.remove' ).all();
	}

	// Remove coupons if they exist.
	let coupons = await page.locator( '.woocommerce-remove-coupon' ).all();

	while ( coupons.length ) {
		await coupons[ 0 ].click();
		await isUIUnblocked( page );

		coupons = await page.locator( '.woocommerce-remove-coupon' ).all();
	}

	await expect( page.locator( '.cart-empty.woocommerce-info' ) ).toHaveText(
		'Your cart is currently empty.'
	);
};
