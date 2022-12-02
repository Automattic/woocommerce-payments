/* global wcpayWooPayExpressParams */

/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @return {*}         The value of the parameter of null.
 */
export const getWooPayExpressData = ( name ) => {
	if ( 'undefined' === typeof wcpayWooPayExpressParams ) {
		return undefined;
	}

	return wcpayWooPayExpressParams?.[ name ];
};
