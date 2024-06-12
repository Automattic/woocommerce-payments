/**
 * External dependencies
 */
import config from 'config';
import { shopperWCP } from './flows';

const { shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

// WooCommerce Checkout
export async function fillCardDetails( page, card ) {
	if (
		await page.$(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element'
		)
	) {
		const frameHandle = await page.waitForSelector(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
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
		await page.waitFor( 1000 );

		const cardCvcInput = await stripeFrame.waitForSelector(
			'[name="cvc"]'
		);
		await cardCvcInput.type( card.cvc, { delay: 20 } );
		await page.waitFor( 1000 );

		const zip = await stripeFrame.$( '[name="postalCode"]' );
		if ( zip !== null ) {
			await zip.type( '90210', { delay: 20 } );
		}
	}
}

// Clear WC Checkout Card Details
export async function clearCardDetails() {
	if ( await page.$( '#payment #wcpay-card-element' ) ) {
		const frameHandle = await page.waitForSelector(
			'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();

		const cardNumberInput = await stripeFrame.waitForSelector(
			'[name="cardnumber"]'
		);
		await cardNumberInput.click();
		await page.waitFor( 1000 );
		await cardNumberInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );

		const cardDateInput = await stripeFrame.waitForSelector(
			'[name="exp-date"]'
		);
		await page.waitFor( 1000 );
		await cardDateInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );

		const cardCvcInput = await stripeFrame.waitForSelector(
			'[name="cvc"]'
		);
		await page.waitFor( 1000 );
		await cardCvcInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );

		await page.waitFor( 1000 );
	} else {
		// Handling Stripe UPE element
		const frameHandle = await page.waitForSelector(
			'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe[name^="__privateStripeFrame"]'
		);
		const stripeFrame = await frameHandle.contentFrame();
		const cardNumberInput = await stripeFrame.waitForSelector(
			'[name="number"]',
			{ timeout: 30000 }
		);
		await cardNumberInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );

		const cardDateInput = await stripeFrame.waitForSelector(
			'[name="expiry"]',
			{ timeout: 30000 }
		);
		await cardDateInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );

		const cardCvcInput = await stripeFrame.waitForSelector(
			'[name="cvc"]',
			{ timeout: 30000 }
		);
		await cardCvcInput.click( { clickCount: 3 } );
		await page.keyboard.press( 'Backspace' );
		await page.waitFor( 1000 );
	}
}
export async function fillCardDetailsPayForOrder( page, card ) {
	await page.waitForSelector( '.__PrivateStripeElement' );
	const frameHandle = await page.waitForSelector(
		'#payment .payment_method_woocommerce_payments .wcpay-upe-element iframe'
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

	const cardCvcInput = await stripeFrame.waitForSelector( '[name="cvc"]' );
	await cardCvcInput.type( card.cvc, { delay: 20 } );
	await page.waitFor( 1000 );

	const zip = await stripeFrame.$( '[name="postalCode"]' );
	if ( zip !== null ) {
		await zip.type( '90210', { delay: 20 } );
	}
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

export async function confirmCardAuthentication( page, authorize = true ) {
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
	const challengeFrame = await challengeFrameHandle.contentFrame();
	// Need to wait for the CSS animations to complete.
	await page.waitFor( 500 );
	const button = await challengeFrame.waitForSelector( target );
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

export async function setupProductCheckoutNoMiniCart(
	billingDetails,
	lineItems = [ [ config.get( 'products.simple.name' ), 1 ] ]
) {
	// Add items to the cart
	for ( const line of lineItems ) {
		const [ productTitle ] = line;
		await shopper.goToShop();
		await shopperWCP.addToCartBySlug( productTitle );
	}
	await shopper.goToCart();
	for ( const line of lineItems ) {
		const [ productTitle, qty ] = line;
		await shopper.setCartQuantity( productTitle, qty );
	}
	await setupCheckout( billingDetails );
}

// Set up checkout
export async function setupCheckout( billingDetails ) {
	await shopper.goToCheckout();
	await uiUnblocked();
	await fillBillingDetails( billingDetails );

	// Woo core blocks and refreshes the UI after 1s after each key press in a text field or immediately after a select
	// field changes. Need to wait to make sure that all key presses were processed by that mechanism.
	await page.waitFor( 1000 );
	await uiUnblocked();
	await expect( page ).toClick(
		'.wc_payment_method.payment_method_woocommerce_payments'
	);
}

// Copy of the fillBillingDetails function from woocommerce/e2e-utils/src/flows/shopper.js
// Supporting countries that do not have a state select input.
// Remove after https://github.com/woocommerce/woocommerce/pull/44090 is merged.
async function fillBillingDetails( customerBillingDetails ) {
	await expect( page ).toFill(
		'#billing_first_name',
		customerBillingDetails.firstname
	);
	await expect( page ).toFill(
		'#billing_last_name',
		customerBillingDetails.lastname
	);
	await expect( page ).toFill(
		'#billing_company',
		customerBillingDetails.company
	);
	await expect( page ).toSelect(
		'#billing_country',
		customerBillingDetails.country
	);
	await expect( page ).toFill(
		'#billing_address_1',
		customerBillingDetails.addressfirstline
	);
	await expect( page ).toFill(
		'#billing_address_2',
		customerBillingDetails.addresssecondline
	);
	await expect( page ).toFill( '#billing_city', customerBillingDetails.city );
	if ( customerBillingDetails.state ) {
		await expect( page ).toSelect(
			'#billing_state',
			customerBillingDetails.state
		);
	}
	await expect( page ).toFill(
		'#billing_postcode',
		customerBillingDetails.postcode
	);
	await expect( page ).toFill(
		'#billing_phone',
		customerBillingDetails.phone
	);
	await expect( page ).toFill(
		'#billing_email',
		customerBillingDetails.email
	);
}

/**
 * Selects the payment method on the checkout page.
 *
 * @param {*} paymentMethod The payment method to select.
 * @param {*} page The page reference object.
 */
export async function selectOnCheckout( paymentMethod, page ) {
	await page.$(
		'#payment .payment_method_woocommerce_payments_' + paymentMethod
	);
	const radioLabel = await page.waitForSelector(
		'#payment .payment_method_woocommerce_payments_' +
			paymentMethod +
			' label'
	);
	radioLabel.click();
	await page.waitFor( 1000 );
}

/**
 * Authorizes or fails a redirected payment.
 *
 * @param {*} page The page reference object.
 * @param {string} action Either of 'success' or 'failure'.
 */
export async function completeRedirectedPayment( page, action ) {
	await page.$( '.actions .common-ButtonGroup' );
	const actionButton = await page.waitForSelector(
		`.actions .common-ButtonGroup a[name=${ action }]`
	);
	actionButton.click();
	await page.waitFor( 1000 );
}
