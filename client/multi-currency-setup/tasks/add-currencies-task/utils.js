/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';

/**
 * Builds the string representation of a currency object, e.g. "US Dollars ($ USD)"
 *
 * @param {Object} currency The currency object to build its string representation.
 * @return {string} The string representation of the currency object.
 */
export const StringRepresentationOfCurrency = ( currency ) => {
	if ( currency && currency.name && currency.symbol && currency.code ) {
		const name = currency.name;
		const hintText =
			currency.code === currency.symbol
				? currency.code
				: sprintf( '%s %s', currency.symbol, currency.code );
		return sprintf( '%s (%s)', name, hintText );
	}
	return '';
};

/**
 * Returns a concatenated list of currencies
 *
 * @param {Array} currencies The currency codes to be represented in a sentence
 * @param {string} except The currency code to exclude
 * @param {Object} currenciesData Currency info container object (code, symbol, name etc)
 * @return {string} The concatenated currency representations list.
 */
export const ConcatenateCurrencyStrings = (
	currencies,
	except,
	currenciesData
) => {
	const filteredCurrencies = currencies.filter(
		( code ) => code !== except && currenciesData[ code ]
	);
	const __and = __( 'and', 'woocommerce-payments' );
	return filteredCurrencies
		.map( ( code ) =>
			StringRepresentationOfCurrency( currenciesData[ code ] )
		)
		.join( ', ' )
		.replace(
			/, ([^,]+)$/,
			2 === filteredCurrencies.length
				? ' ' + __and + ' $1'
				: ', ' + __and + ' $1'
		);
};
