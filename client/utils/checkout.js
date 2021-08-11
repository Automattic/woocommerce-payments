/* global wcpay_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	const config =
		'undefined' !== typeof wcpay_config
			? wcpay_config
			: wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config[ name ] || null;
};

/**
 * Forms dynamic gateway title for UPE checkout from enabled methods
 *
 * @param {Object} paymentMethodsConfig Object containing map of enabled UPE payment methods to settings.
 * @return {string} Dynamic title string dependent on payment methods enabled.
 */
export const getCustomGatewayTitle = ( paymentMethodsConfig ) => {
	const enabledPaymentMethods = Object.keys( paymentMethodsConfig ).sort();
	let label = '';

	if ( 2 > enabledPaymentMethods.length ) {
		label = paymentMethodsConfig[ enabledPaymentMethods[ 0 ] ].title;
	} else {
		label = getConfig( 'checkoutTitle' );
	}

	return label;
};
