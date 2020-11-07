/**
 * External dependencies
 */
import config from 'config';

/**
 * Internal dependencies
 */
import { CustomerFlow, uiUnblocked } from './index';

export async function fillCardDetails( page, card ) {
	const frameHandle = await page.waitForSelector(
		'#payment iframe[name^="__privateStripeFrame"]'
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
	if ( '3DS' === cardType ) {
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

export async function setupProductCheckout( billingDetails ) {
	await CustomerFlow.goToShop();
	await CustomerFlow.addToCartFromShopPage(
		config.get( 'products.simple.name' )
	);
	await CustomerFlow.goToCheckout();
	await uiUnblocked();
	await CustomerFlow.fillBillingDetails( billingDetails );
	await uiUnblocked();
	await expect( page ).toClick(
		'.wc_payment_method.payment_method_woocommerce_payments'
	);
}
