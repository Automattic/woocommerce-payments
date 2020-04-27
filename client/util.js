
/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { capitalize } from 'lodash';

/**
 * Returns true if WooCommerce Payments is in test mode, false otherwise.
 *
 * @param {boolean} fallback Test mode fallback value in case test mode value can't be found.
 *
 * @returns {boolean} True if in test mode, false otherwise. Fallback value if test mode value can't be found.
 */
export const isInTestMode = ( fallback = false ) => {
	if ( 'undefined' === typeof wcpaySettings ) {
		return fallback;
	}
	return '1' === wcpaySettings.testMode || fallback;
};

/**
 * Returns the URL to the WooCommerce Payments settings.
 *
 * @returns {string} URL to the WooCommerce Payments settings menu.
 */
export const getPaymentSettingsUrl = () => {
	return addQueryArgs(
		'admin.php',
		{
			page: 'wc-settings',
			tab: 'checkout',
			section: 'woocommerce_payments',
		}
	);
};

/**
 * Basic formatting function to convert snake_case to display value.
 *
 * @param {string} value snake_case string to convert.
 *
 * @return {string} Display string for rendering.
 */
export const formatStringValue = ( value ) => capitalize( value ).replace( /_/g, ' ' );

/**
 * Adds value to query
 *
 * @param {object} query query object
 * @param {object} formattedQuery object to add the value
 * @param {string} source key for the value in the query object
 * @param {string} key key to add the value into formattedQuery
 * @param {object} config query config object
 */
const addValueToQuery = ( query, formattedQuery, source, key, config ) => {
	let value = query[ source ];
	if ( config.isNumber ) {
		value = Number.isNaN( parseInt( value, 10 ) ) ? undefined : value;
	}
	formattedQuery[ key ] = value || config.default;
};

/**
 * Returns values from query formatted based on a config object
 *
 * @param {object} query query object
 * @param {object} config query config object
 *
 * @return {object} query object
 */
export const getFormattedQuery = ( query, config ) => {
	const formattedQuery = {};
	for ( const [ key, cfg ] of Object.entries( config ) ) {
		if ( cfg.isFilter ) {
			cfg.rules.forEach( rule => {
				const fieldName = `${ cfg.source }_${ rule }`;
				addValueToQuery( query, formattedQuery, fieldName, fieldName, cfg );
			} );
		} else {
			addValueToQuery( query, formattedQuery, cfg.source, key, cfg );
		}
	}
	return formattedQuery;
};
