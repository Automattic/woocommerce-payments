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
	const name = currency.name;
	const hintText =
		currency.code === currency.symbol
			? currency.code
			: sprintf( '%s %s', currency.symbol, currency.code );
	return sprintf( '%s (%s)', name, hintText );
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
	const currenciesToList = currencies.filter( ( code ) => code !== except );
	if ( 0 === currenciesToList.length ) return '';
	if ( 1 === currenciesToList.length )
		return StringRepresentationOfCurrency(
			currenciesData[ currenciesToList[ 0 ] ]
		);
	if ( 2 === currenciesToList.length )
		return currenciesToList
			.map( ( d ) => currenciesData[ d ] )
			.map( StringRepresentationOfCurrency )
			.join( __( ' and ' ) );

	const lastCurrency = currenciesData[ currenciesToList.pop() ];
	return (
		currenciesToList
			.map( ( d ) => currenciesData[ d ] )
			.map( StringRepresentationOfCurrency )
			.join( ', ' ) +
		', ' +
		__( 'and', 'woocommerce-payments' ) +
		' ' +
		StringRepresentationOfCurrency( lastCurrency )
	);
};
