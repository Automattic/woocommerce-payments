/**
 * Internal dependencies
 */
import { getExpressCheckoutData } from './express-checkout-data';

export type ExpressPaymentType =
	| 'apple_pay'
	| 'google_pay'
	| 'amazon_pay'
	| 'paypal'
	| 'link';

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param expressPaymentType Can be either 'apple_pay', 'google_pay', 'amazon_pay', 'paypal' or 'link'.
 */
export const displayLoginConfirmation = (
	expressPaymentType: ExpressPaymentType
) => {
	const loginConfirmation = getExpressCheckoutData( 'login_confirmation' );

	if ( ! loginConfirmation ) {
		return;
	}

	const paymentTypesMap = {
		apple_pay: 'Apple Pay',
		google_pay: 'Google Pay',
		amazon_pay: 'Amazon Pay',
		paypal: 'PayPal',
		link: 'Link',
	};
	let message = loginConfirmation.message;

	// Replace dialog text with specific express checkout type.
	message = message.replace(
		/\*\*.*?\*\*/,
		paymentTypesMap[ expressPaymentType ]
	);

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = loginConfirmation.redirect_url;
	}
};
