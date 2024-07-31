/**
 * External dependencies
 */
import { Page } from 'playwright/test';

/**
 * Internal dependencies
 */

import { config, CustomerAddress } from '../config/default';

export const fillBillingAddress = async (
	page: Page,
	billingAddress: CustomerAddress
): Promise< void > => {
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

export const placeOrder = async ( page: Page ): Promise< void > => {
	await page.locator( '#place_order' ).click();
};

export const addCartProduct = async (
	page: Page,
	productId = 16 // Beanie
): Promise< void > => {
	await page.goto( `/shop/?add-to-cart=${ productId }` );
};

export const fillCardDetails = async (
	page: Page,
	card = config.cards.basic
): Promise< void > => {
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
): Promise< void > => {
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
