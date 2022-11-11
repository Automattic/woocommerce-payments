/**
 * External dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import crypto from 'crypto';
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

export const decryptClientSecret = function (
	encryptedValue,
	stripeAccountId = null
) {
	if (
		getConfig( 'isClientEncryptionEnabled' ) &&
		3 < encryptedValue.length &&
		'pi_' !== encryptedValue.slice( 0, 3 ) &&
		'seti_' !== encryptedValue.slice( 0, 5 )
	) {
		stripeAccountId = stripeAccountId || getConfig( 'accountId' );

		const decipher = crypto.createDecipheriv(
			'aes-128-cbc',
			stripeAccountId.slice( 5 ),
			'WC'.repeat( 8 )
		);

		return (
			decipher.update( encryptedValue, 'base64', 'utf8' ) +
			decipher.final( 'utf8' )
		);
	}
	return encryptedValue;
};
