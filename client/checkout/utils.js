/* global wcpay_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @returns {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// eslint-disable-next-line camelcase
	const config = ( 'undefined' !== typeof wcpay_config )
		// eslint-disable-next-line camelcase
		? wcpay_config // Classic checkout
		: wc.wcSettings.getSetting( 'woocommerce_payments_data' ); // Blocks

	return config[ name ] || null;
};
