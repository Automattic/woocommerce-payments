/**
 * Generates terms parameter for UPE, with value set for reusable payment methods
 *
 * @param {Object} paymentMethodsConfig Object mapping payment method strings to their settings.
 * @param {string} value The terms value for each available payment method.
 * @return {Object} Terms parameter fit for UPE.
 */
export const getTerms = ( paymentMethodsConfig, value = 'always' ) => {
	const reusablePaymentMethods = Object.keys( paymentMethodsConfig ).filter(
		( method ) => paymentMethodsConfig[ method ].isReusable
	);

	return reusablePaymentMethods.reduce( ( obj, method ) => {
		obj[ method ] = value;
		return obj;
	}, {} );
};

/**
 * Returns the value of the given cookie.
 *
 * @param {string} name Name of the cookie.
 *
 * @return {string} Value of the given cookie. Empty string if cookie doesn't exist.
 */
export const getCookieValue = ( name ) =>
	document.cookie.match( '(^|;)\\s*' + name + '\\s*=\\s*([^;]+)' )?.pop() ||
	'';

/**
 * Check if Card payment is being used.
 *
 * @return {boolean} Boolean indicating whether or not Card payment is being used.
 */
export const isWCPayChosen = function () {
	return document.getElementById( 'payment_method_woocommerce_payments' )
		.checked;
};

/**
 * Returns the cached payment intent for the current cart state.
 *
 * @param {Object} paymentMethodsConfig Array of configs for payment methods.
 * @param {string} paymentMethodType Type of the payment method.
 * @return {Object} The intent id and client secret required for mounting the UPE element.
 */
export const getPaymentIntentFromSession = (
	paymentMethodsConfig,
	paymentMethodType
) => {
	const cartHash = getCookieValue( 'woocommerce_cart_hash' );
	const upePaymentIntentData =
		paymentMethodsConfig[ paymentMethodType ].upePaymentIntentData;

	if (
		cartHash &&
		upePaymentIntentData &&
		upePaymentIntentData.startsWith( cartHash )
	) {
		const intentId = upePaymentIntentData.split( '-' )[ 1 ];
		const clientSecret = upePaymentIntentData.split( '-' )[ 2 ];
		return { intentId, clientSecret };
	}

	return {};
};
