/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import Currency, { getCurrencyData } from '@woocommerce/currency';
import { find, trimEnd, endsWith, values } from 'lodash';

const currencyData = [
	...values( getCurrencyData() ),
	{
		code: 'NZD',
		symbol: '$',
		symbolPosition: 'left',
		thousandSeparator: ',',
		decimalSeparator: '.',
		precision: 2,
	},
	{
		code: 'AUD',
		symbol: '$',
		symbolPosition: 'left',
		thousandSeparator: ',',
		decimalSeparator: '.',
		precision: 2,
	},
];

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
 *
 * @return {Currency|null} Currency object
 */
export const getCurrency = ( currencyCode ) => {
	const currency = find( currencyData, { code: currencyCode.toUpperCase() } );
	if ( currency ) {
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
 * @param {number}  amount       Amount
 * @param {string}  currencyCode Currency code
 * @param {boolean} omitCode     Whether to render fallback without currency code
 *
 * @return {string} formatted currency representation
 */
export const formatCurrency = (
	amount,
	currencyCode = 'USD',
	omitCode = false
) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	const currency = getCurrency( currencyCode );
	if ( null === currency ) {
		return composeFallbackCurrency(
			amount,
			omitCode ? null : currencyCode,
			isZeroDecimal
		);
	}

	try {
		const formattedAmount =
			'function' === typeof currency.formatAmount
				? currency.formatAmount( amount )
				: currency.formatCurrency( amount );

		if (
			omitCode ||
			wcSettings.currency.code === currencyCode.toUpperCase()
		) {
			return formattedAmount;
		}

		return `${ currencyCode.toUpperCase() } ${ formattedAmount }`;
	} catch ( err ) {
		return composeFallbackCurrency(
			amount,
			omitCode ? null : currencyCode,
			isZeroDecimal
		);
	}
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
	return `${ formatCurrency(
		fromAmount,
		from.currency
	) } → ${ formatExchangeRate( from, to ) }: ${ formatCurrency(
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
			isZeroDecimal ? '%s %i' : '%s %.5f',
			to.currency.toUpperCase(),
			exchangeRate
		);
	}
	const exchangeCurrency = new Currency( {
		...exchangeCurrencyConfig,
		precision,
	} );
	return trimEndingZeroes( exchangeCurrency.formatAmount( exchangeRate ) );
}

function composeFallbackCurrency( amount, currencyCode, isZeroDecimal ) {
	// Fallback for unsupported currencies: currency code and amount
	const numericFormat = isZeroDecimal ? '%1$i' : '%1$.2f';
	const format = currencyCode ? `%2$s ${ numericFormat }` : numericFormat;
	return sprintf( format, amount, currencyCode?.toUpperCase() );
}

function trimEndingZeroes( formattedCurrencyAmount = '' ) {
	return formattedCurrencyAmount
		.split( ' ' )
		.map( ( chunk ) =>
			endsWith( chunk, '0' ) ? trimEnd( chunk, '0' ) : chunk
		)
		.join( ' ' );
}
