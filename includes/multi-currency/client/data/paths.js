/** @format */

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { InvalidPathSegmentError } from 'wcpay/utils/path-segment';
import { NAMESPACE } from './constants';

/**
 * Returns the REST path for a single currency's settings.
 *
 * The currency code can come from the page URL, so it is limited to the three letters the
 * server route accepts.
 *
 * @param {string} currencyCode The currency code.
 *
 * @return {string} The REST path.
 *
 * @throws {InvalidPathSegmentError} When the code is not three letters.
 */
export const getCurrencySettingsPath = ( currencyCode ) => {
	if (
		typeof currencyCode !== 'string' ||
		! /^[A-Za-z]{3}$/.test( currencyCode )
	) {
		throw new InvalidPathSegmentError(
			__( 'Invalid currency code.', 'woocommerce-payments' )
		);
	}

	return `${ NAMESPACE }/multi-currency/currencies/${ currencyCode }`;
};
