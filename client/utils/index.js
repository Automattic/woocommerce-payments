/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { capitalize } from 'lodash';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';

/**
 * Returns true if WooCommerce Payments is in test mode, false otherwise.
 *
 * @param {boolean} fallback Test mode fallback value in case test mode value can't be found.
 *
 * @returns {boolean} True if in test mode, false otherwise. Fallback value if test mode value can't be found.
 */
export const isInTestMode = ( fallback = false ) => {
	if ( 'undefined' === typeof wcpaySettings ) {
		return fallback;
	}
	return '1' === wcpaySettings.testMode || fallback;
};

/**
 * Returns the URL to the WooCommerce Payments settings.
 *
 * @returns {string} URL to the WooCommerce Payments settings menu.
 */
export const getPaymentSettingsUrl = () => {
	return addQueryArgs( 'admin.php', {
		page: 'wc-settings',
		tab: 'checkout',
		section: 'woocommerce_payments',
	} );
};

/**
 * Returns the URL to the list of payment methods page.
 *
 * @returns {string} URL to the list of payment methods page.
 */
export const getPaymentMethodsUrl = () => {
	return addQueryArgs( 'admin.php', {
		page: 'wc-settings',
		tab: 'checkout',
	} );
};

/**
 * Basic formatting function to convert snake_case to display value.
 *
 * @param {string} value snake_case string to convert.
 *
 * @return {string} Display string for rendering.
 */
export const formatStringValue = ( value ) =>
	capitalize( value ).replace( /_/g, ' ' );

/**
 * Basic formatting function to convert local date string to UTC.
 *
 * We want the selected date to be included in upper bound selections, so we need to make its time 11:59:59 PM.
 *
 * @param {string} date       date string to be formatted.
 * @param {bool}   upperBound flag to include the selected day for upper bound dates.
 *
 * @return {string} Formatted date string to use in server query.
 */
export const formatDateValue = ( date, upperBound = false ) => {
	const adjustedDate = upperBound
		? moment( date ).endOf( 'day' ).utc()
		: moment( date ).startOf( 'day' ).utc();
	return date && dateI18n( 'Y-m-d H:i:s', adjustedDate );
};
