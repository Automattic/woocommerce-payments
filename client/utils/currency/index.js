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
 * Gets wc-admin Currency for the given currency code
 *
 * @param {String} currencyCode Currency code
 *
 * @return {boolean} true if currency is zero-decimal
 */
const isZeroDecimalCurrency = ( currencyCode ) => {
	return wcpaySettings.zeroDecimalCurrencies.includes(
		currencyCode.toLowerCase()
	);
};

/**
 * Gets wc-admin Currency for the given currency code
 *
 * @param {Number} amount       Amount
 * @param {String} currencyCode Currency code
 *
 * @return {String} formatted currency representation
 */
export const formatCurrency = ( amount, currencyCode ) => {
	// Normalize amount with respect to zer decimal currencies and provided data formats
	const isZeroDecimal = isZeroDecimalCurrency( currencyCode );
	if ( isZeroDecimal ) {
		amount *= 100;
	}
	amount /= 100;

	const currency = getCurrency( currencyCode );
	if ( currency === null ) {
		// Fallback for unsupported currencies: currency code and amount
		return sprintf(
			isZeroDecimal ? '%s %i' : '%s %.2f',
			currencyCode.toUpperCase(),
			amount
		);
	}
	return currency.formatCurrency( amount );
};
