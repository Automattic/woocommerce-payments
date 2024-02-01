/* global wcpayConfig, wcpay_upe_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	let config = null;
	if ( typeof wcpayConfig !== 'undefined' ) {
		config = wcpayConfig;
	} else if ( typeof wcpay_upe_config !== 'undefined' ) {
		config = wcpay_upe_config;
	} else if ( typeof wc !== 'undefined' ) {
		config = wc.wcSettings.getSetting( 'woocommerce_payments_data' );
	} else {
		return null;
	}

	return config[ name ] || null;
};

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getUPEConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	const config =
		typeof wcpay_upe_config !== 'undefined'
			? wcpay_upe_config
			: wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config[ name ] || null;
};
