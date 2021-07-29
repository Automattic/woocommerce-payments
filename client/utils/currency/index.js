/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { find, trimEnd, endsWith } from 'lodash';

const currencyData = getCurrencyData();

const currencyNames = {
	aud: 'Australian dollar',
	cad: 'Canadian dollar',
	chf: 'Swiss franc',
	dkk: 'Danish krone',
	eur: 'Euro',
	gbp: 'Pound sterling',
	nok: 'Norwegian krone',
	nzd: 'New Zealand dollar',
	sek: 'Swedish krona',
	usd: 'United States (US) dollar',
};

/**
 * Formats and translates currency code name.
 *
 * @param {string} currencyCode Currency code
 *
 * @return {string} formatted and translated currency name
 */
export const formatCurrencyName = ( currencyCode ) => {
	const currencyName =
		currencyNames[ currencyCode.toLowerCase() ] ||
		currencyCode.toUpperCase();
	// eslint-disable-next-line @wordpress/i18n-no-variables
	return __( currencyName, 'woocommerce-payments' );
};

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {string} currencyCode Currency code
 * @param {string} baseCurrencyCode Base Currency code to override decimal and thousand separators
 *
 * @return {Currency|null} Currency object
 */
export const getCurrency = ( currencyCode, baseCurrencyCode = null ) => {
	const currency = find( currencyData, { code: currencyCode.toUpperCase() } );
	if ( currency ) {
		if (
			null !== baseCurrencyCode &&
			baseCurrencyCode.toUpperCase() !== currencyCode.toUpperCase()
		) {
			const baseCurrency = find( currencyData, {
				code: baseCurrencyCode.toUpperCase(),
			} );
			if ( baseCurrency ) {
				currency.decimalSeparator = baseCurrency.decimalSeparator;
				currency.thousandSeparator = baseCurrency.thousandSeparator;
				currency.symbolPosition = baseCurrency.symbolPosition;
				if ( 0 !== currency.precision ) {
					currency.precision = baseCurrency.precision;
				}
			}
		}
		return new Currency( currency );
	}
	return null;
};

/**
 * Determines if the given currency is zero decimal.
 *
 * @param {string} currencyCode Currency code
 *
 * @return {boolean} true if currency is zero-decimal
 */
const isZeroDecimalCurrency = ( currencyCode ) => {
	return wcpaySettings.zeroDecimalCurrencies.includes(
		currencyCode.toLowerCase()
	);
};

/**
 * Formats amount according to the given currency.
 *
 * @param {number} amount       Amount
 * @param {string} currencyCode Currency code
 * @param {string} baseCurrencyCode Base Currency code to override decimal and thousand separators
 *
 * @return {string} formatted currency representation
 */
export const formatCurrency = (
	amount,
	currencyCode = 'USD',
	baseCurrencyCode = null
) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	const currency = getCurrency( currencyCode, baseCurrencyCode );
	if ( null === currency ) {
		return composeFallbackCurrency( amount, currencyCode, isZeroDecimal );
	}

	try {
		return 'function' === typeof currency.formatAmount
			? currency.formatAmount( amount )
			: currency.formatCurrency( amount );
	} catch ( err ) {
		return composeFallbackCurrency( amount, currencyCode, isZeroDecimal );
	}
};

/**
 * Appends the currency code if it's not present in the current price.
 *
 * @param {string} formatted Preformatted price string
 * @param {string} currencyCode Currency code to append
 *
 * @return {string} formatted currency representation with the currency code suffix
 */
const appendCurrencyCode = ( formatted, currencyCode ) => {
	if ( -1 === formatted.toString().indexOf( currencyCode ) ) {
		formatted = formatted + ' ' + currencyCode;
	}
	return formatted;
};

/**
 * Formats amount according to the given currency.
 *
 * @param {number} amount       Amount
 * @param {string} currencyCode Currency code
 * @param {boolean} skipSymbol  If true, trims off the short currency symbol
 * @param {string} baseCurrencyCode Base Currency code to override decimal and thousand separators
 *
 * @return {string} formatted currency representation
 */
export const formatExplicitCurrency = (
	amount,
	currencyCode = 'USD',
	skipSymbol = false,
	baseCurrencyCode = null
) => {
	let formatted = formatCurrency( amount, currencyCode, baseCurrencyCode );
	if ( skipSymbol ) {
		formatted = removeCurrencySymbol( formatted );
	}
	return appendCurrencyCode( formatted, currencyCode.toUpperCase() );
};

/**
 * Formats exchange rate string from one currency to another.
 *
 * @param {Object} from          Source currency and amount for exchange rate calculation.
 * @param {string} from.currency Source currency code.
 * @param {number} from.amount   Source amount.
 * @param {Object} to            Target currency and amount for exchange rate calculation.
 * @param {string} to.currency   Target currency code.
 * @param {number} to.amount     Target amount.
 *
 * @return {string?} formatted string like `€1,00 → $1,19: $29.99`.
 *
 * */
export const formatFX = ( from, to ) => {
	if ( ! from.currency || ! to.currency ) {
		return;
	}

	const fromAmount = isZeroDecimalCurrency( from.currency ) ? 1 : 100;
	return `${ formatExplicitCurrency(
		fromAmount,
		from.currency,
		true
	) } → ${ formatExchangeRate( from, to ) }: ${ formatExplicitCurrency(
		Math.abs( to.amount ),
		to.currency
	) }`;
};

function formatExchangeRate( from, to ) {
	let exchangeRate =
		'number' === typeof to.amount &&
		'number' === typeof from.amount &&
		0 !== from.amount
			? Math.abs( to.amount / from.amount )
			: 0;
	if ( isZeroDecimalCurrency( to.currency ) ) {
		exchangeRate *= 100;
	}

	if ( isZeroDecimalCurrency( from.currency ) ) {
		exchangeRate /= 100;
	}

	const exchangeCurrencyConfig = find( currencyData, {
		code: to.currency.toUpperCase(),
	} );

	const precision = 1 > exchangeRate ? 6 : 5;
	const isZeroDecimal = isZeroDecimalCurrency( to.currency );

	if ( ! exchangeCurrencyConfig ) {
		sprintf(
			isZeroDecimal ? '%i %s' : '%.5f %s',
			exchangeRate,
			to.currency.toUpperCase()
		);
	}
	const exchangeCurrency = new Currency( {
		...exchangeCurrencyConfig,
		precision,
	} );
	return appendCurrencyCode(
		trimEndingZeroes(
			removeCurrencySymbol(
				exchangeCurrency.formatAmount( exchangeRate )
			)
		),
		to.currency.toUpperCase()
	);
}

function removeCurrencySymbol( formatted ) {
	formatted = formatted.replace( /[^0-9,.' ]/g, '' ).trim();
	return formatted;
}

function composeFallbackCurrency( amount, currencyCode, isZeroDecimal ) {
	// Fallback for unsupported currencies: currency code and amount
	return amount.toLocaleString( undefined, {
		style: 'currency',
		currency: currencyCode,
		currencyDisplay: 'narrowSymbol',
		dummy: isZeroDecimal,
	} );
}

function trimEndingZeroes( formattedCurrencyAmount = '' ) {
	return formattedCurrencyAmount
		.split( ' ' )
		.map( ( chunk ) =>
			endsWith( chunk, '0' ) ? trimEnd( chunk, '0' ) : chunk
		)
		.join( ' ' );
}
