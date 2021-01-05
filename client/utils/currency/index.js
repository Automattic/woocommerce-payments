/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { find } from 'lodash';

const currencyData = getCurrencyData();

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {String} currencyCode Currency code
 *
 * @return {Currency} Currency object
 */
export const getCurrency = ( currencyCode ) => {
	const currency = find( currencyData, { code: currencyCode.toUpperCase() } );
	if ( currency ) {
		return new Currency( currency );
	}
	window.console.warn(
		sprintf(
			'"%s" is not supported by @woocommerce/currency, falling back to "USD"',
			currencyCode
		)
	);
	return new Currency();
};

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {String} currencyCode Currency code
 *
 * @return {boolean} true if currency is zero-decimal
 */
export const isZeroDecimalCurrency = ( currencyCode ) => {
	const zeroDecimalCurrencies = [
		'bif',
		'clp',
		'djf',
		'gnf',
		'jpy',
		'kmf',
		'krw',
		'mga',
		'pyg',
		'rwf',
		'ugx',
		'vnd',
		'vuv',
		'xaf',
		'xof',
		'xpf',
	];
	return zeroDecimalCurrencies.includes( currencyCode.toLowerCase() );
};
