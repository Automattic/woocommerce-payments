/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Generates a currency tab title.
 *
 * @param {string} currencyCode The currency code.
 * @return {string} The currency tab title. Example: "USD balance"
 */
export const getCurrencyTabTitle = ( currencyCode: string ): string => {
	return sprintf(
		/** translators: %s is the currency code, e.g. USD. */
		__( '%s Balance', 'woocommerce-payments' ),
		currencyCode.toUpperCase()
	);
};
