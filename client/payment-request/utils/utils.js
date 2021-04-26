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
