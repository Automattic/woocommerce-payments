/**
 * External dependencies
 */
import config from 'config';

const { shopper, uiUnblocked } = require( '@woocommerce/e2e-utils' );

export async function fillCardDetails( page, card ) {
	const frameHandle = await page.waitForSelector(
		'#payment #wcpay-card-element iframe[name^="__privateStripeFrame"]'
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

// Set up checkout with simple product
export async function setupProductCheckout( billingDetails ) {
	await shopper.goToShop();
	await shopper.addToCartFromShopPage( config.get( 'products.simple.name' ) );
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
