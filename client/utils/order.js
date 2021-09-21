/* global wcpay_order_config, wc */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	// Config for the Edit Order screen.
	const config =
		wcpay_order_config ?? // eslint-disable-line camelcase
		wc.wcSettings.getSetting( 'woocommerce_payments_data' );

	return config?.[ name ];
};
