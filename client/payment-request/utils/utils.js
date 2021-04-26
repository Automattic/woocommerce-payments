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
 * Gets Stripe Payment request options object.
 *
 * @return {Object} Payment Request options object
 */
export const getPaymentRequestOptions = () => {
	// Get total and displayItems for product page or cart/checkout page.
	const data = getPaymentRequestData( 'is_product_page' )
		? getPaymentRequestData( 'product' )
		: getPaymentRequestData( 'cart' );

	let country = getPaymentRequestData( 'checkout' )?.country_code;

	// Puerto Rico (PR) is the only US territory/possession that's supported by Stripe.
	// Since it's considered a US state by Stripe, we need to do some special mapping.
	if ( 'PR' === country ) {
		country = 'US';
	}

	return {
		total: data.total,
		currency: getPaymentRequestData( 'checkout' )?.currency_code,
		country: country,
		requestPayerName: true,
		requestPayerEmail: true,
		requestPayerPhone: getPaymentRequestData( 'checkout' )
			?.needs_payer_phone,
		requestShipping: getPaymentRequestData( 'checkout' )?.needs_shipping,
		displayItems: data.displayItems,
	};
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
