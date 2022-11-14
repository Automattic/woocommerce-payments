/**
 * External dependencies
 */
import { getConfig } from 'wcpay/utils/checkout';
import aesjs from 'aes-js';

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
		return aesjs.utils.utf8.fromBytes(
			aesjs.padding.pkcs7.strip(
				/* eslint-disable-next-line */
				new aesjs.ModeOfOperation.cbc(
					aesjs.utils.utf8.toBytes( stripeAccountId.slice( 5 ) ),
					aesjs.utils.utf8.toBytes( 'WC'.repeat( 8 ) )
				).decrypt(
					Uint16Array.from( atob( encryptedValue ), ( c ) =>
						c.charCodeAt( 0 )
					)
				)
			)
		);
	}
	return encryptedValue;
};
