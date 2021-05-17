/* global wcpayPaymentRequestParams */

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getPaymentRequestData = ( key ) => {
	if (
		'object' === typeof wcpayPaymentRequestParams &&
		wcpayPaymentRequestParams.hasOwnProperty( key )
	) {
		return wcpayPaymentRequestParams[ key ];
	}
	return null;
};

/**
 * The total PaymentItem object used for the Stripe PaymentRequest object.
 *
 * @param {int} total  The total amount.
 * @return {Object} The total object used for Stripe.
 */
const getTotalPaymentItem = ( total ) => {
	return {
		label: getPaymentRequestData( 'total_label' ),
		amount: total,
	};
};

/**
 * Returns a Stripe payment request object.
 *
 * @param {Object} config A configuration object for getting the payment request.
 * @return {Object} Payment Request options object
 */
export const getPaymentRequest = ( {
	stripe,
	total,
	requestShipping,
	displayItems,
} ) => {
	let country = getPaymentRequestData( 'checkout' )?.country_code;

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( 'PR' === country ) {
		country = 'US';
	}

	const options = {
		total: getTotalPaymentItem( total ),
		currency: getPaymentRequestData( 'checkout' )?.currency_code,
		country,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getPaymentRequestData( 'checkout' )
			?.needs_payer_phone,
		requestShipping,
		displayItems,
	};

	return stripe.paymentRequest( options );
};

/**
 * Utility function for updating the Stripe PaymentRequest object
 *
 * @param {Object} update An object containing the things needed for the update.
 */
export const updatePaymentRequest = ( {
	paymentRequest,
	total,
	displayItems,
} ) => {
	paymentRequest.update( {
		total: getTotalPaymentItem( total ),
		displayItems,
	} );
};

/**
 * Returns whether or not the current session can make payments and what type of request it uses.
 *
 * @param {Object} paymentRequest A Stripe PaymentRequest instance.
 *
 * @return {Promise} Object containing canPay and the requestType, which can be either
 * - payment_request_api
 * - apple_pay
 * - google_pay
 */
export const canDoPaymentRequest = ( paymentRequest ) => {
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
			} else {
				resolve( { canPay: false } );
			}
		} );
	} );
};

/**
 * Get WC AJAX endpoint URL.
 *
 * @param {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getPaymentRequestAjaxURL = ( endpoint ) =>
	getPaymentRequestData( 'wc_ajax_url' )
		.toString()
		.replace( '%%endpoint%%', 'wcpay_' + endpoint );

/**
 * Get error messages from WooCommerce notice from server response.
 *
 * @param {string} notice Error notice.
 * @return {string} Error messages.
 */
export const getErrorMessageFromNotice = ( notice ) => {
	const div = document.createElement( 'div' );
	div.innerHTML = notice.trim();
	return div.firstChild ? div.firstChild.textContent : '';
};

/**
 * Whether or not to use Google Pay branded button in Chrome.
 *
 * @return {boolean} Use Google Pay button in Chrome.
 */
export const shouldUseGooglePayBrand = () => {
	const ua = window.navigator.userAgent.toLowerCase();
	const isChrome =
		/chrome/.test( ua ) &&
		! /edge|edg|opr|brave\//.test( ua ) &&
		'Google Inc.' === window.navigator.vendor;
	// newer versions of Brave do not have the userAgent string
	const isBrave = isChrome && window.navigator.brave;
	return isChrome && ! isBrave;
};
