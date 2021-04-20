/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { normalizeLineItems } from '../utils';

/**
 * @typedef {import('./type-defs').StripePaymentItem} StripePaymentItem
 * @typedef {import('./type-defs').StripePaymentRequest} StripePaymentRequest
 * @typedef {import('@woocommerce/type-defs/registered-payment-method-props').PreparedCartTotalItem} CartTotalItem
 */

/**
 * The total PaymentItem object used for the stripe PaymentRequest object.
 *
 * @param {CartTotalItem} total  The total amount.
 *
 * @return {StripePaymentItem} The PaymentItem object used for stripe.
 */
const getTotalPaymentItem = ( total ) => {
	return {
		label:
			// - TODO: Get total label
			// getStripeServerData().stripeTotalLabel ||
			__( 'Total', 'woocommerce-payments' ),
		amount: total.value,
	};
};

/**
 * Returns a stripe payment request object
 *
 * @param {Object}          config                  A configuration object for
 *                                                  getting the payment request.
 * @param {Object}          config.stripe           The stripe api.
 * @param {CartTotalItem}   config.total            The amount for the total
 *                                                  (in subunits) provided by
 *                                                  checkout/cart.
 * @param {string}          config.currencyCode     The currency code provided
 *                                                  by checkout/cart.
 * @param {string}          config.countryCode      The country code provided by
 *                                                  checkout/cart.
 * @param {boolean}         config.shippingRequired Whether or not shipping is
 *                                                  required.
 * @param {CartTotalItem[]} config.cartTotalItems   Array of line items provided
 *                                                  by checkout/cart.
 *
 * @return {StripePaymentRequest} A stripe payment request object
 */
const getPaymentRequest = ( {
	stripe,
	total,
	currencyCode,
	countryCode,
	shippingRequired,
	cartTotalItems,
} ) => {
	const options = {
		total: getTotalPaymentItem( total ),
		currency: currencyCode,
		country: countryCode || 'US',
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: true,
		requestShipping: shippingRequired,
		displayItems: normalizeLineItems( cartTotalItems ),
	};
	return stripe.paymentRequest( options );
};

/**
 * Utility function for updating the Stripe PaymentRequest object
 *
 * @param {Object}               update                An object containing the
 *                                                     things needed for the
 *                                                     update
 * @param {StripePaymentRequest} update.paymentRequest A Stripe payment request
 *                                                     object
 * @param {CartTotalItem}        update.total          A total line item.
 * @param {string}               update.currencyCode   The currency code for the
 *                                                     amount provided.
 * @param {CartTotalItem[]}      update.cartTotalItems An array of line items
 *                                                     provided by the
 *                                                     cart/checkout.
 */
const updatePaymentRequest = ( {
	paymentRequest,
	total,
	currencyCode,
	cartTotalItems,
} ) => {
	paymentRequest.update( {
		total: getTotalPaymentItem( total ),
		currency: currencyCode,
		displayItems: normalizeLineItems( cartTotalItems ),
	} );
};

/**
 * Returns whether or not the current session can make payments and what type of request it uses.
 *
 * @param {StripePaymentRequest} paymentRequest A Stripe PaymentRequest instance.
 *
 * @return {Promise<Object>} Object containing canPay and the requestType, which can be either
 * - payment_request_api
 * - apple_pay
 * - google_pay
 */
const canDoPaymentRequest = ( paymentRequest ) => {
	return new Promise( ( resolve ) => {
		paymentRequest.canMakePayment().then( ( result ) => {
			if ( result ) {
				let paymentRequestType = 'payment_request_api';
				if ( result.applePay ) {
					paymentRequestType = 'apple_pay';
				} else if ( result.googlePay ) {
					paymentRequestType = 'google_pay';
				}

				resolve( { canPay: true, requestType: paymentRequestType } );
				return;
			}
			resolve( { canPay: false } );
		} );
	} );
};

// const isNonFriendlyError = ( type ) =>
// 	[
// 		errorTypes.INVALID_REQUEST,
// 		errorTypes.API_CONNECTION,
// 		errorTypes.API_ERROR,
// 		errorTypes.AUTHENTICATION_ERROR,
// 		errorTypes.RATE_LIMIT_ERROR,
// 	].includes( type );

// const getErrorMessageForCode = ( code ) => {
// 	const messages = {
// 		[ errorCodes.INVALID_NUMBER ]: __(
// 			'The card number is not a valid credit card number.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INVALID_EXPIRY_MONTH ]: __(
// 			'The card expiration month is invalid.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INVALID_EXPIRY_YEAR ]: __(
// 			'The card expiration year is invalid.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INVALID_CVC ]: __(
// 			'The card security code is invalid.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCORRECT_NUMBER ]: __(
// 			'The card number is incorrect.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCOMPLETE_NUMBER ]: __(
// 			'The card number is incomplete.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCOMPLETE_CVC ]: __(
// 			'The card security code is incomplete.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCOMPLETE_EXPIRY ]: __(
// 			'The card expiration date is incomplete.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.EXPIRED_CARD ]: __(
// 			'The card has expired.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCORRECT_CVC ]: __(
// 			'The card security code is incorrect.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INCORRECT_ZIP ]: __(
// 			'The card zip code failed validation.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.INVALID_EXPIRY_YEAR_PAST ]: __(
// 			'The card expiration year is in the past',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.CARD_DECLINED ]: __(
// 			'The card was declined.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.MISSING ]: __(
// 			'There is no card on a customer that is being charged.',
// 			'woocommerce-payments'
// 		),
// 		[ errorCodes.PROCESSING_ERROR ]: __(
// 			'An error occurred while processing the card.',
// 			'woocommerce-payments'
// 		),
// 	};
// 	return messages[ code ] || null;
// };

// const getErrorMessageForTypeAndCode = ( type, code = '' ) => {
// 	switch ( type ) {
// 		case errorTypes.INVALID_EMAIL:
// 			return __(
// 				'Invalid email address, please correct and try again.',
// 				'woo-gutenberg-product-blocks'
// 			);
// 		case isNonFriendlyError( type ):
// 			return __(
// 				'Unable to process this payment, please try again or use alternative method.',
// 				'woo-gutenberg-product-blocks'
// 			);
// 		case errorTypes.CARD_ERROR:
// 			return getErrorMessageForCode( code );
// 		case errorTypes.VALIDATION_ERROR:
// 			return ''; // These are shown inline.
// 	}
// 	return null;
// };

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param {string} notice Error notice.
 * @return {string} Error messages.
 */
const getErrorMessageFromNotice = ( notice ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

/**
 * pluckAddress takes a full address object and returns relevant fields for calculating
 * shipping, so we can track when one of them change to update rates.
 *
 * @param {Object} address          An object containing all address information
 *
 * @return {Object} pluckedAddress  An object containing shipping address that are needed to fetch an address.
 */
// const pluckAddress = ( { country, state, city, postcode } ) => ( {
// 	country,
// 	state,
// 	city,
// 	postcode: postcode.replace( ' ', '' ).toUpperCase(),
// } );

export {
	getTotalPaymentItem,
	getPaymentRequest,
	updatePaymentRequest,
	canDoPaymentRequest,
	// getErrorMessageForTypeAndCode,
	getErrorMessageFromNotice,
	// pluckAddress,
};
