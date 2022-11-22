/* global wcpayWooPayExpressParams, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getWooPayExpressData = ( name ) => {
	// Config for the Edit Order screen.
	const config =
		wcpayWooPayExpressParams ??
		wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config?.[ name ];
};
