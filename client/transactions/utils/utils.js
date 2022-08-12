/**
 * External dependencies
 */
import { partial } from 'lodash';
import { numberFormat } from '@woocommerce/number';

/**
 * Applies country-specific thousand separator to the transactions number
 *
 * @param {number} trxCount The number of transactions.
 * @return {number} Number of transactions with the country-specific thousand separator.
 */
export const applyThousandSeparator = ( trxCount ) => {
	const siteLang = document.documentElement.lang;
	const siteNumberOptions = {
		thousandSeparator: ',',
	};

	if ( [ 'fr', 'pl' ].some( ( lang ) => siteLang.startsWith( lang ) ) ) {
		siteNumberOptions.thousandSeparator = ' ';
	} else if ( 'de-CH' === siteLang ) {
		siteNumberOptions.thousandSeparator = "'";
	} else if (
		[ 'de', 'nl', 'it', 'es', 'pt' ].some( ( lang ) =>
			siteLang.startsWith( lang )
		)
	) {
		siteNumberOptions.thousandSeparator = '.';
	}

	const formattedNumber = partial( numberFormat, siteNumberOptions );
	return formattedNumber( trxCount );
};
