/* global wcpayPaymentRequestParams */

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}          The value of the parameter or null.
 */
export const getPaymentRequestData = ( name ) => {
	if (
		'object' === typeof wcpayPaymentRequestParams &&
		wcpayPaymentRequestParams.hasOwnProperty( name )
	) {
		return wcpayPaymentRequestParams[ name ];
	}
	return null;
};

/**
 * Get WC AJAX endpoint URL.
 *
 * @param  {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getAjaxURL = ( endpoint ) =>
	getPaymentRequestData( 'ajax_url' )
		.toString()
		.replace( '%%endpoint%%', 'wcpay_' + endpoint );
