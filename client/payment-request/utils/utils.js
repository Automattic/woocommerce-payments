/**
 * Get WC AJAX endpoint URL.
 *
 * @param  {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getAjaxURL = ( endpoint ) =>
	// eslint-disable-next-line no-undef
	wcpayPaymentRequestParams.ajax_url
		.toString()
		.replace( '%%endpoint%%', 'wcpay_' + endpoint );
