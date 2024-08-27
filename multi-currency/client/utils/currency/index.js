/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import Currency from '@woocommerce/currency';
import { endsWith, find, trimEnd } from 'lodash';

const currencyNames = {
	aud: __( 'Australian dollar', 'woocommerce-payments' ),
	cad: __( 'Canadian dollar', 'woocommerce-payments' ),
	chf: __( 'Swiss franc', 'woocommerce-payments' ),
	dkk: __( 'Danish krone', 'woocommerce-payments' ),
	eur: __( 'Euro', 'woocommerce-payments' ),
	gbp: __( 'Pound sterling', 'woocommerce-payments' ),
	nok: __( 'Norwegian krone', 'woocommerce-payments' ),
	nzd: __( 'New Zealand dollar', 'woocommerce-payments' ),
	sek: __( 'Swedish krona', 'woocommerce-payments' ),
	usd: __( 'United States (US) dollar', 'woocommerce-payments' ),
};

/**
 * Formats and translates currency code name.
 *
 * @param {string} currencyCode Currency code
 *
 * @return {string} formatted and translated currency name
 */
export const formatCurrencyName = ( currencyCode ) =>
	currencyNames[ currencyCode.toLowerCase() ] || currencyCode.toUpperCase();

/* eslint-disable valid-jsdoc */
/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {string} currencyCode Currency code
 * @param {string} baseCurrencyCode Base Currency code to override decimal and thousand separators
 *
 */
export const getCurrency = ( currencyCode, baseCurrencyCode = null ) => {
	const {
		currencyData,
		connect: { country = 'US' },
	} = wcpaySettings;

	const currency = find( currencyData, { code: currencyCode.toUpperCase() } );
	if ( currency ) {
		if (
			( baseCurrencyCode !== null &&
				baseCurrencyCode.toUpperCase() !==
					currencyCode.toUpperCase() ) ||
			currencyData[ country ]
		) {
			const baseCurrency = baseCurrencyCode
				? find( currencyData, {
						code: baseCurrencyCode.toUpperCase(),
				  } )
				: currencyData[ country ];

			if ( baseCurrency ) {
				currency.decimalSeparator = baseCurrency.decimalSeparator;
				currency.thousandSeparator = baseCurrency.thousandSeparator;
				currency.symbolPosition = baseCurrency.symbolPosition;
			}
		}
		return Currency( currency );
	}
	return null;
};
/* eslint-enable valid-jsdoc */

/**
 * Gets wc-admin currency for the given currency code. Unlike `getCurrency`, this function
 * will return the currency based only on the currency itself and locale. This means that the
 * currency object will not be modified based on the store's country settings,
 * or on another given currency.
 *
 * @param {string} currencyCode Currency code
 * @return {Object} Currency object.
 */
export const getCurrencyByLocale = ( currencyCode ) => {
	const code = currencyCode.toUpperCase();
	const {
		currencyData,
		connect: { country = 'US' },
	} = wcpaySettings;

	// If store's country currency matches the provided currency code, return store's country currency.
	if ( currencyData[ country ]?.code === code ) {
		return Currency( currencyData[ country ] );
	}

	const currency = find( currencyData, { code } );

	if ( currency ) {
		const { defaultLocale = {} } = currency;

		if (
			defaultLocale.hasOwnProperty( 'decimalSeparator' ) &&
			defaultLocale.hasOwnProperty( 'thousandSeparator' ) &&
			defaultLocale.hasOwnProperty( 'symbolPosition' )
		) {
			currency.decimalSeparator = defaultLocale.decimalSeparator;
			currency.thousandSeparator = defaultLocale.thousandSeparator;
			currency.symbolPosition = defaultLocale.symbolPosition;
		}

		return Currency( currency );
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
export const isZeroDecimalCurrency = ( currencyCode ) => {
	return wcpaySettings.zeroDecimalCurrencies.includes(
		currencyCode.toLowerCase()
	);
};

/**
 * Formats the amount for CSV export, considering zero-decimal currencies
 *
 * @param {number} amount       Amount
 * @param {string} currencyCode Currency code
 *
 * @return {number} Export amount
 */
export const formatExportAmount = ( amount, currencyCode ) => {
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );

	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	return amount;
};

/**
 * Formats amount according to the given currency.
 *
 * @param {number} amount       Amount
 * @param {string} currencyCode Currency code
 * @param {string} baseCurrencyCode Base Currency code to override decimal and thousand separators
 * @param {boolean} useLocaleFormatting If `baseCurrencyCode` isn't provided, the currency will be
 * formatted based on the store's country currency. This parameter allows to override this behavior
 * and get the default formatting.
 *
 * @return {string} formatted currency representation
 */
export const formatCurrency = (
	amount,
	currencyCode = 'USD',
	baseCurrencyCode = null,
	useLocaleFormatting = false
) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	const isNegative = amount < 0;
	const positiveAmount = isNegative ? -1 * amount : amount;
	const prefix = isNegative ? '-' : '';
	const currency = useLocaleFormatting
		? getCurrencyByLocale( currencyCode )
		: getCurrency( currencyCode, baseCurrencyCode );

	if ( currency === null ) {
		return (
			prefix +
			composeFallbackCurrency(
				positiveAmount,
				currencyCode,
				isZeroDecimal
			)
		);
	}

	try {
		return (
			prefix +
			( typeof currency.formatAmount === 'function'
				? htmlDecode( currency.formatAmount( positiveAmount ) )
				: htmlDecode( currency.formatCurrency( positiveAmount ) ) )
		);
	} catch ( err ) {
		return (
			prefix +
			htmlDecode(
				composeFallbackCurrency(
					positiveAmount,
					currencyCode,
					isZeroDecimal
				)
			)
		);
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
	if ( formatted.toString().indexOf( currencyCode ) === -1 ) {
		formatted = formatted + ' ' + currencyCode;
	}
	return formatted;
};

/**
 * Formats amount according to the given currency. Falls back to `formatCurrency` when no additional currencies are enabled.
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
	if ( ! wcpaySettings.shouldUseExplicitPrice ) return formatted;
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
	const { currencyData } = wcpaySettings;

	let exchangeRate =
		typeof to.amount === 'number' &&
		typeof from.amount === 'number' &&
		from.amount !== 0
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

	const precision = exchangeRate < 1 ? 6 : 5;
	const isZeroDecimal = isZeroDecimalCurrency( to.currency );

	if ( ! exchangeCurrencyConfig ) {
		sprintf(
			isZeroDecimal ? '%i %s' : '%.5f %s',
			exchangeRate,
			to.currency.toUpperCase()
		);
	}
	const exchangeCurrency = Currency( {
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
	try {
		// Fallback for unsupported currencies: currency code and amount
		return amount.toLocaleString( undefined, {
			style: 'currency',
			currency: currencyCode,
			currencyDisplay: 'narrowSymbol',
			dummy: isZeroDecimal,
		} );
	} catch ( error ) {
		return sprintf(
			isZeroDecimal ? '%s %i' : '%s %.2f',
			currencyCode.toUpperCase(),
			amount
		);
	}
}

export function trimEndingZeroes( formattedCurrencyAmount = '' ) {
	return formattedCurrencyAmount
		.split( ' ' )
		.map( ( chunk ) =>
			endsWith( chunk, '0' ) ? trimEnd( chunk, '0' ) : chunk
		)
		.join( ' ' );
}

function htmlDecode( input ) {
	const doc = new DOMParser().parseFromString( input, 'text/html' );
	return doc.documentElement.textContent;
}
