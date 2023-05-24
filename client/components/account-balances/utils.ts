/**
 * External dependencies
 */
import { sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { currencyBalanceString } from './strings';

/**
 * Generates a currency tab title.
 *
 * @param {string} currencyCode The currency code.
 * @return {string} The currency tab title. Example: "USD balance"
 */
export const getCurrencyTabTitle = ( currencyCode: string ): string => {
	return sprintf(
		// string format: {currency} balance
		currencyBalanceString,
		currencyCode.toUpperCase()
	);
};
