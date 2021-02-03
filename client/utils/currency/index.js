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
 * @param {string} currencyCode Currency code
 *
 * @return {Currency|null} Currency object
 */
const getCurrency = ( currencyCode ) => {
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
 * @param {number} amount       Amount
 * @param {string} currencyCode Currency code
 *
 * @return {string} formatted currency representation
 */
export const formatCurrency = ( amount, currencyCode = 'USD' ) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	const currency = getCurrency( currencyCode );
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
	const fromAmount = isZeroDecimalCurrency( from.currency )
		? from.amount
		: from.amount / 100;
	const toAmount = isZeroDecimalCurrency( to.currency )
		? to.amount
		: to.amount / 100;
	const precision = Math.pow( 10, 4 );
	const exchangeRate = Math.abs(
		Math.round( ( toAmount / fromAmount ) * precision ) / precision
	);

	// TODO: Cover with tests and fix formatting for various cases.
	return `${ formatCurrency( 100, from.currency ) } → ${ formatCurrency(
		exchangeRate * 100,
		to.currency
	) }: ${ formatCurrency( Math.abs( to.amount ), to.currency ) }`;
};

function composeFallbackCurrency( amount, currencyCode, isZeroDecimal ) {
	// Fallback for unsupported currencies: currency code and amount
	return sprintf(
		isZeroDecimal ? '%s %i' : '%s %.2f',
		currencyCode.toUpperCase(),
		amount
	);
}
