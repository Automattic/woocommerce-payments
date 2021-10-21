/**
 * External dependencies
 */
import config from 'config';

const { shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

// WooCommerce Checkout
export async function fillCardDetails( page, card ) {
	if ( await page.$( '#payment #wcpay-upe-element' ) ) {
		const frameHandle = await page.waitForSelector(
			'#payment #wcpay-upe-element iframe'
		);

		const stripeFrame = await frameHandle.contentFrame();

		const cardNumberInput = await stripeFrame.waitForSelector(
			'[name="number"]',
			{ timeout: 30000 }
		);

		await cardNumberInput.type( card.number, { delay: 20 } );

		const cardDateInput = await stripeFrame.waitForSelector(
			'[name="expiry"]'
		);

		await cardDateInput.type( card.expires.month + card.expires.year, {
			delay: 20,
		} );

		const cardCvcInput = await stripeFrame.waitForSelector(
			'[name="cvc"]'
		);
		await cardCvcInput.type( card.cvc, { delay: 20 } );
	} else {
		await page.waitForSelector( '.__PrivateStripeElement' );
		const frameHandle = await page.waitForSelector(
			'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();

		const cardNumberInput = await stripeFrame.waitForSelector(
			'[name="cardnumber"]',
			{ timeout: 30000 }
		);
		await cardNumberInput.type( card.number, { delay: 20 } );

		const cardDateInput = await stripeFrame.waitForSelector(
			'[name="exp-date"]'
		);

		await cardDateInput.type( card.expires.month + card.expires.year, {
			delay: 20,
		} );

		const cardCvcInput = await stripeFrame.waitForSelector(
			'[name="cvc"]'
		);
		await cardCvcInput.type( card.cvc, { delay: 20 } );
	}
}

// Clear WC Checkout Card Details
export async function clearCardDetails() {
	const frameHandle = await page.waitForSelector(
		'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	const cardNumberInput = await stripeFrame.waitForSelector(
		'[name="cardnumber"]'
	);
	const cardDateInput = await stripeFrame.waitForSelector(
		'[name="exp-date"]'
	);
	const cardCvcInput = await stripeFrame.waitForSelector( '[name="cvc"]' );

	await cardNumberInput.click();
	await page.waitFor( 1000 );
	await cardNumberInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );

	await page.waitFor( 1000 );
	await cardDateInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );

	await page.waitFor( 1000 );
	await cardCvcInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );
}

// WooCommerce Blocks Checkout
export async function fillCardDetailsWCB( page, card ) {
	await page.waitForSelector( '.__PrivateStripeElement' );
	const frameHandle = await page.waitForSelector(
		'#payment-method .wcpay-card-mounted iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	const inputs = await stripeFrame.$$( '.InputElement.Input' );

	const [ cardNumberInput, cardDateInput, cardCvcInput ] = inputs;
	await cardNumberInput.type( card.number, { delay: 20 } );
	await cardDateInput.type( card.expires.month + card.expires.year, {
		delay: 20,
	} );
	await cardCvcInput.type( card.cvc, { delay: 20 } );
}

// Clear WC Checkout Card Details
export async function clearWCBCardDetails() {
	const frameHandle = await page.waitForSelector(
		'#payment-method .wcpay-card-mounted iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	const inputs = await stripeFrame.$$( '.InputElement.Input' );
	const [ cardNumberInput, cardDateInput, cardCvcInput ] = inputs;

	await page.waitFor( 1000 );
	await cardNumberInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );

	await page.waitFor( 1000 );
	await cardDateInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );

	await page.waitFor( 1000 );
	await cardCvcInput.click( { clickCount: 3 } );
	await page.keyboard.press( 'Backspace' );
}

export async function confirmCardAuthentication(
	page,
	cardType = '3DS',
	authorize = true
) {
	const target = authorize
		? '#test-source-authorize-3ds'
		: '#test-source-fail-3ds';

	// Stripe card input also uses __privateStripeFrame as a prefix, so need to make sure we wait for an iframe that
	// appears at the top of the DOM.
	const frameHandle = await page.waitForSelector(
		'body>div>iframe[name^="__privateStripeFrame"]'
	);
	const stripeFrame = await frameHandle.contentFrame();
	const challengeFrameHandle = await stripeFrame.waitForSelector(
		'iframe#challengeFrame'
	);
	let challengeFrame = await challengeFrameHandle.contentFrame();
	// 3DS 1 cards have another iframe enclosing the authorize form
	if ( '3DS' === cardType.toUpperCase() ) {
		const acsFrameHandle = await challengeFrame.waitForSelector(
			'iframe[name="acsFrame"]'
		);
		challengeFrame = await acsFrameHandle.contentFrame();
	}
	const button = await challengeFrame.waitForSelector( target );
	// Need to wait for the CSS animations to complete.
	await page.waitFor( 500 );
	await button.click();
}

/**
 * Set up checkout with any number of products.
 *
 * @param {any} billingDetails Values to be entered into the 'Billing details' form in the Checkout page
 * @param {any} lineItems A 2D array of line items where each line item is an array
 * that contains the product title as the first element, and the quantity as the second.
 * For example, if you want to checkout the products x2 "Hoodie" and x3 "Belt" then you can set this `lineItems` parameter like this:
 *
 * `[ [ "Hoodie", 2 ], [ "Belt", 3 ] ]`.
 *
 * Default value is 1 piece of `config.get( 'products.simple.name' )`.
 */
export async function setupProductCheckout(
	billingDetails,
	lineItems = [ [ config.get( 'products.simple.name' ), 1 ] ]
) {
	const cartItemsCounter = '.cart-contents .count';

	await shopper.goToShop();

	// Get the current number of items in the cart
	let cartSize = await page.$eval( cartItemsCounter, ( e ) =>
		Number( e.innerText.replace( /\D/g, '' ) )
	);

	// Add items to the cart
	for ( const line of lineItems ) {
		let [ productTitle, qty ] = line;

		while ( qty-- ) {
			await shopper.addToCartFromShopPage( productTitle );

			// Make sure that the number of items in the cart is incremented first before adding another item.
			await expect( page ).toMatchElement( cartItemsCounter, {
				text: new RegExp( `${ ++cartSize } items?` ),
				timeout: 30000,
			} );
		}
	}

	await setupCheckout( billingDetails );
}

// Set up checkout
export async function setupCheckout( billingDetails ) {
	await shopper.goToCheckout();
	await uiUnblocked();
	await shopper.fillBillingDetails( billingDetails );
	// Woo core blocks and refreshes the UI after 1s after each key press in a text field or immediately after a select
	// field changes. Need to wait to make sure that all key presses were processed by that mechanism.
	await page.waitFor( 1000 );
	await uiUnblocked();
	await expect( page ).toClick(
		'.wc_payment_method.payment_method_woocommerce_payments'
	);
}
