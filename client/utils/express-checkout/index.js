/* global wcpayPaymentRequestParams, wcpayExpressCheckoutParams */

/**
 * Retrieves express checkout config from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getExpressCheckoutConfig = ( key ) => {
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

export const getDefaultBorderRadius = () => {
	return parseInt(
		window?.wcpaySettings?.defaultExpressCheckoutBorderRadius || 4,
		10
	);
};

/**
 * Get WC AJAX endpoint URL for express checkout endpoints.
 *
 * @param {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getExpressCheckoutAjaxURL = ( endpoint ) =>
	getExpressCheckoutConfig( 'wc_ajax_url' )
		.toString()
		.replace( '%%endpoint%%', 'wcpay_' + endpoint );

/**
 * Retrieves payment request data from global variable.
 *
 * @param {string} key The object property key.
 * @return {mixed} Value of the object prop or null.
 */
export const getPaymentRequestData = ( key ) => getExpressCheckoutConfig( key );

/**
 * Get WC AJAX endpoint URL.
 *
 * @param {string} endpoint Endpoint.
 * @return {string} URL with interpolated endpoint.
 */
export const getPaymentRequestAjaxURL = ( endpoint ) =>
	getExpressCheckoutAjaxURL( endpoint );

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
		window.navigator.vendor === 'Google Inc.';
	// newer versions of Brave do not have the userAgent string
	const isBrave = isChrome && window.navigator.brave;
	return isChrome && ! isBrave;
};

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
