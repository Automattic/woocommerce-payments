/* global wcpayPaymentRequestParams, wcpayExpressCheckoutParams */

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getPaymentRequestData = ( key ) => {
	if (
		typeof wcpayExpressCheckoutParams === 'object' &&
		wcpayExpressCheckoutParams.hasOwnProperty( key )
	) {
		return wcpayExpressCheckoutParams[ key ];
	}
	if (
		typeof wcpayPaymentRequestParams === 'object' &&
		wcpayPaymentRequestParams.hasOwnProperty( key )
	) {
		return wcpayPaymentRequestParams[ key ];
	}
	return null;
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
 * Construct WC AJAX endpoint URL.
 *
 * @param {string} ajaxURL AJAX URL.
 * @param {string} endpoint Request endpoint URL.
 * @param {string} prefix Optional prefix for endpoint action.
 * @return {string} URL with interpolated ednpoint.
 */
export const buildAjaxURL = ( ajaxURL, endpoint, prefix = 'wcpay_' ) =>
	ajaxURL.toString().replace( '%%endpoint%%', prefix + endpoint );
