/**
 * Retrieves a configuration value.
 *
 * @param {string} name The name of the config parameter.
 * @returns {*}         The value of the parameter of null.
 */
export const getConfig = ( name ) => {
	return wcpay_config[ name ] || null;
}
