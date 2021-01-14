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
		// eslint-disable-next-line camelcase
		wcpay_config || wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config[ name ] || null;
};

export const setConfig = ( name, value ) => {
	// eslint-disable-next-line camelcase
	if ( typeof wcpay_config !== 'undefined' ) {
		// Classic checkout
		// eslint-disable-next-line camelcase
		wcpay_config[ name ] = value;
	} else {
		// Blocks
		const config = wc.wcSettings.getSetting( 'woocommerce_payments_data' );
		config[ name ] = value;
		wc.wcSettings.setSetting( 'woocommerce_payments_data', config );
	}
};
