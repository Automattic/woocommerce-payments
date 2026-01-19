/**
 * Internal dependencies
 */
import { getUPEConfig } from 'wcpay/utils/checkout';

/**
 * Generates terms for reusable payment methods
 *
 * @param {Object} paymentMethodsConfig Object mapping payment method strings to their settings.
 * @param {string} value The terms value for each available payment method.
 * @return {Object} Terms parameter fit for UPE.
 */
export const getTerms = ( paymentMethodsConfig, value = 'always' ) => {
	const reusablePaymentMethods = Object.keys( paymentMethodsConfig ).filter(
		( method ) =>
			// Stripe link doesn't need the "terms" - adding this property causes a warning in the console.
			method !== 'link' && paymentMethodsConfig[ method ].isReusable
	);

	return reusablePaymentMethods.reduce( ( obj, method ) => {
		obj[ method ] = value;
		return obj;
	}, {} );
};

/**
 * Check whether Stripe Link is enabled.
 *
 * @param {Object} paymentMethodsConfig Checkout payment methods configuration settings object.
 * @return {boolean} True, if enabled; false otherwise.
 */
export const isLinkEnabled = ( paymentMethodsConfig ) => {
	return (
		paymentMethodsConfig.link !== undefined &&
		paymentMethodsConfig.card !== undefined
	);
};

/**
 * Get array of payment method types to use with intent.
 *
 * @param {string} paymentMethodType Payment method type Stripe ID.
 * @return {Array} Array of payment method types to use with intent.
 */
export const getPaymentMethodTypes = ( paymentMethodType ) => {
	const paymentMethodTypes = [ paymentMethodType ];
	if (
		paymentMethodType === 'card' &&
		isLinkEnabled( getUPEConfig( 'paymentMethodsConfig' ) )
	) {
		paymentMethodTypes.push( 'link' );
	}
	return paymentMethodTypes;
};
