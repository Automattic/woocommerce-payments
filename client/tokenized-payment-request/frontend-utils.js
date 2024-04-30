/* global wcpayPaymentRequestParams */
/**
 * Internal dependencies
 */
import { transformCartDataForDisplayItems } from './transformers/wc-to-stripe';

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getPaymentRequestData = ( key ) => {
	if (
		typeof wcpayPaymentRequestParams === 'object' &&
		wcpayPaymentRequestParams.hasOwnProperty( key )
	) {
		return wcpayPaymentRequestParams[ key ];
	}
	return null;
};

/**
 * Returns a Stripe payment request object.
 *
 * @param {Object} config A configuration object for getting the payment request.
 * @return {Object} Payment Request options object
 */
export const getPaymentRequest = ( { stripe, cartData, productData } ) => {
	// the country code defined here comes from the WC settings.
	// It might be interesting to ensure the country code coincides with the Stripe account's country,
	// as defined here: https://docs.stripe.com/js/payment_request/create
	let country = getPaymentRequestData( 'checkout' )?.country_code;

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( country === 'PR' ) {
		country = 'US';
	}

	return stripe.paymentRequest( {
		country,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getPaymentRequestData( 'checkout' )
			?.needs_payer_phone,
		...( productData
			? {
					// we can't just pass `productData`, and we need a little bit of massaging for older data.
					currency: productData.currency,
					total: productData.total,
					displayItems: productData.displayItems,
					requestShipping: productData.needs_shipping,
			  }
			: {
					currency: cartData.totals.currency_code.toLowerCase(),
					total: {
						label: getPaymentRequestData( 'total_label' ),
						amount: parseInt( cartData.totals.total_price, 10 ),
					},
					requestShipping:
						wcpayPaymentRequestParams.button_context ===
						'pay_for_order'
							? false
							: cartData.needs_shipping,
					displayItems: transformCartDataForDisplayItems( cartData ),
			  } ),
	} );
};

/**
 * Displays a `confirm` dialog which leads to a redirect.
 *
 * @param {string} paymentRequestType Can be either apple_pay, google_pay or payment_request_api.
 */
export const displayLoginConfirmationDialog = ( paymentRequestType ) => {
	if ( ! getPaymentRequestData( 'login_confirmation' ) ) {
		return;
	}

	let message = getPaymentRequestData( 'login_confirmation' )?.message;

	// Replace dialog text with specific payment request type "Apple Pay" or "Google Pay".
	if ( paymentRequestType !== 'payment_request_api' ) {
		message = message.replace(
			/\*\*.*?\*\*/,
			paymentRequestType === 'apple_pay' ? 'Apple Pay' : 'Google Pay'
		);
	}

	// Remove asterisks from string.
	message = message.replace( /\*\*/g, '' );

	if ( confirm( message ) ) {
		// Redirect to my account page.
		window.location.href = getPaymentRequestData(
			'login_confirmation'
		)?.redirect_url;
	}
};
