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
	if ( currencies.includes( except ) )
		currencies.splice( currencies.indexOf( except ), 1 );
	if ( 0 === currencies.length ) return '';
	if ( 1 === currencies.length )
		return StringRepresentationOfCurrency(
			currenciesData[ currencies[ 0 ] ]
		);
	if ( 2 === currencies.length )
		return currencies
			.map( ( d ) => currenciesData[ d ] )
			.map( StringRepresentationOfCurrency )
			.join( __( ' and ' ) );

	const lastCurrency = currenciesData[ currencies.pop() ];
	return (
		currencies
			.map( ( d ) => currenciesData[ d ] )
			.map( StringRepresentationOfCurrency )
			.join( ', ' ) +
		', ' +
		__( 'and', 'woocommerce-payments' ) +
		' ' +
		StringRepresentationOfCurrency( lastCurrency )
	);
};
