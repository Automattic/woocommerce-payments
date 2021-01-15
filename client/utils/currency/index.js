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
export const formatCurrency = ( amount, currencyCode ) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( ! isZeroDecimal ) {
		amount /= 100;
	}

	const currency = getCurrency( currencyCode );
	if ( null === currency ) {
		// Fallback for unsupported currencies: currency code and amount
		return sprintf(
			isZeroDecimal ? '%s %i' : '%s %.2f',
			currencyCode.toUpperCase(),
			amount
		);
	}
	return currency.formatCurrency( amount );
};
