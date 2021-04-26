/* global wcpayPaymentRequestParams */

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {*}         The value of the parameter key or null.
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
 * Get WC AJAX endpoint URL.
 *
 * @param  {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getPaymentRequestAjaxURL = ( endpoint ) =>
	getPaymentRequestData( 'wc_ajax_url' )
		.toString()
		.replace( '%%endpoint%%', 'wcpay_' + endpoint );
