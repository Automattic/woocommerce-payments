/* global wcpayConfig, wcpay_upe_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	if ( typeof wcpayConfig !== 'undefined' ) {
		return wcpayConfig[ name ];
	}

	// eslint-disable-next-line no-use-before-define
	return getUPEConfig( name );
};

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getUPEConfig = ( name ) => {
	// Classic checkout or blocks-based one.
	let config = null;
	if ( typeof wcpay_upe_config !== 'undefined' ) {
		config = wcpay_upe_config;
	} else if (
		typeof wc === 'object' &&
		typeof wc.wcSettings !== 'undefined'
	) {
		// If getSettings or woocommerce_payments_data is not available, default to an empty object so we return null bellow.
		config = wc.wcSettings.getSetting( 'woocommerce_payments_data' ) || {};
	} else {
		return null;
	}

	return config[ name ] || null;
};
