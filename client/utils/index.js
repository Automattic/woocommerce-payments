/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { capitalize, partial } from 'lodash';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';
import { NAMESPACE } from 'wcpay/data/constants';
import { numberFormat } from '@woocommerce/number';

/**
 * Returns true if WooPayments is in test mode, false otherwise.
 *
 * @param {boolean} fallback Test mode fallback value in case test mode value can't be found.
 *
 * @return {boolean} True if in test mode, false otherwise. Fallback value if test mode value can't be found.
 */
export const isInTestMode = ( fallback = false ) => {
	if ( typeof wcpaySettings === 'undefined' ) {
		return fallback;
	}
	return wcpaySettings.testMode === '1' || fallback;
};

/**
 * Returns true if WooPayments is in dev mode, false otherwise.
 *
 * @param {boolean} fallback Fallback in case dev mode value can't be found (for example if the wcpaySettings are undefined).
 *
 * @return {boolean} True if in dev mode, false otherwise. Fallback value if test/dev mode value can't be found.
 */
export const isInDevMode = ( fallback = false ) => {
	if ( typeof wcpaySettings === 'undefined' ) {
		return fallback;
	}
	return wcpaySettings.devMode === '1' || fallback;
};

export const getAdminUrl = ( args ) => addQueryArgs( 'admin.php', args );

/**
 * Returns the URL to view a WooPayments document.
 *
 * @param {string} documentId The document ID.
 *
 * @return {string} The URL to view the document.
 */
export const getDocumentUrl = ( documentId ) => {
	// Remove trailing slash from wpApiSettings.root since NAMESPACE already includes it.
	const baseUrl = `${ wpApiSettings.root.replace( /\/$/, '' ) }`;
	return addQueryArgs(
		`${ baseUrl }${ NAMESPACE }/documents/${ documentId }`,
		{
			_wpnonce: wpApiSettings.nonce,
		}
	);
};

/**
 * Returns the URL to the WooPayments settings.
 *
 * @return {string} URL to the WooPayments settings menu.
 */
export const getPaymentSettingsUrl = () => {
	return getAdminUrl( {
		page: 'wc-settings',
		tab: 'checkout',
		section: 'woocommerce_payments',
	} );
};

/**
 * Returns the URL to a specific payment method's settings page.
 *
 * @param {string} method Payment method ID.
 *
 * @return {string} URL to the payment method's settings page.
 */
export const getPaymentMethodSettingsUrl = ( method ) => {
	return getAdminUrl( {
		page: 'wc-settings',
		tab: 'checkout',
		section: 'woocommerce_payments',
		method,
	} );
};

/**
 * Returns the URL to the list of payment methods page.
 *
 * @return {string} URL to the list of payment methods page.
 */
export const getPaymentMethodsUrl = () => {
	return getAdminUrl( {
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
		? moment( date ).endOf( 'day' ).utc().toISOString()
		: moment( date ).startOf( 'day' ).utc().toISOString();
	return (
		date &&
		dateI18n(
			'Y-m-d H:i:s',
			adjustedDate,
			true // TODO Change call to gmdateI18n and remove this deprecated param once WP 5.4 support ends.
		)
	);
};

/**
 * Applies country-specific thousand separator to the transactions number
 *
 * @param {number} trxCount The number of transactions.
 * @return {number} Number of transactions with the country-specific thousand separator.
 */
export const applyThousandSeparator = ( trxCount ) => {
	const siteLang = document.documentElement.lang;
	const siteNumberOptions = {
		thousandSeparator: ',',
	};

	if ( [ 'fr', 'pl' ].some( ( lang ) => siteLang.startsWith( lang ) ) ) {
		siteNumberOptions.thousandSeparator = ' ';
	} else if ( siteLang === 'de-CH' ) {
		siteNumberOptions.thousandSeparator = "'";
	} else if (
		[ 'de', 'nl', 'it', 'es', 'pt' ].some( ( lang ) =>
			siteLang.startsWith( lang )
		)
	) {
		siteNumberOptions.thousandSeparator = '.';
	}

	const formattedNumber = partial( numberFormat, siteNumberOptions );
	return formattedNumber( trxCount );
};

/**
 * Returns true if Export Modal is dismissed, false otherwise.
 *
 * @return {boolean} True if dismissed, false otherwise.
 */
export const isExportModalDismissed = () => {
	if ( typeof wcpaySettings === 'undefined' ) {
		return false;
	}
	return wcpaySettings?.reporting?.exportModalDismissed ?? false;
};
