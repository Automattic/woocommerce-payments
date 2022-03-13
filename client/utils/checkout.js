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
