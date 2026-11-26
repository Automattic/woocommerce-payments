/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { capitalize, partial } from 'lodash';
import moment from 'moment';
import * as PlainDate from 'temporal-polyfill/fns/PlainDate';
import { getBasic } from 'temporal-polyfill/fns/Calendar';
import { NAMESPACE } from 'wcpay/data/constants';
import { encodePathSegment } from 'utils/path-segment';
import { numberFormat } from '@woocommerce/number';

/**
 * Returns whether a value is an object.
 *
 * @see https://stackoverflow.com/a/22482737 for the source of the approach and explanations.
 *
 * @param {any} value The value to check.
 * @return {boolean} Whether the value is an object.
 */
export const isObject = ( value ) => {
	if ( value === null ) {
		return false;
	}
	return typeof value === 'function' || typeof value === 'object';
};

/**
 * Returns true if WooPayments uses a test [drive] account, false otherwise.
 *
 * @return {boolean} True if a test [drive] account is connected, false otherwise.
 */
export const hasTestAccount = () => {
	const accountStatus = wcpaySettings?.accountStatus;

	if ( ! wcpaySettings?.isAccountConnected || ! isObject( accountStatus ) ) {
		return false;
	}

	// A test [drive] account is one that is not live and is marked as such.
	return ! accountStatus?.isLive && !! accountStatus?.testDrive;
};

/**
 * Returns true if WooPayments uses a sandbox [test] account, false otherwise.
 *
 * @return {boolean} True if a sandbox [test] account is connected, false otherwise.
 */
export const hasSandboxAccount = () => {
	const accountStatus = wcpaySettings?.accountStatus;

	if ( ! wcpaySettings?.isAccountConnected || ! isObject( accountStatus ) ) {
		return false;
	}

	// A sandbox [test] account is one that is not live and is not a test [drive] account.
	return ! accountStatus?.isLive && ! accountStatus?.testDrive;
};

/**
 * Returns true if WooPayments is in test mode, false otherwise.
 *
 * @param {boolean} fallback Test mode fallback value in case test mode value can't be found.
 *
 * @return {boolean} True if in test mode, false otherwise. Fallback value if test mode value can't be found.
 */
export const isInTestMode = ( fallback = false ) => {
	if (
		! isObject( wcpaySettings ) ||
		! wcpaySettings.hasOwnProperty( 'testMode' )
	) {
		return fallback;
	}

	return !! wcpaySettings.testMode || fallback;
};

/**
 * Returns true if WooPayments is in test mode onboarding, false otherwise.
 *
 * @param {boolean} fallback Fallback in case test mode onboarding value can't be found
 *                           (for example if the wcpaySettings are undefined).
 *
 * @return {boolean} True if in test mode onboarding, false otherwise.
 * 					 Fallback value if test mode onboarding value can't be found.
 */
export const isInTestModeOnboarding = ( fallback = false ) => {
	if (
		! isObject( wcpaySettings ) ||
		! wcpaySettings.hasOwnProperty( 'testModeOnboarding' )
	) {
		return fallback;
	}

	return !! wcpaySettings.testModeOnboarding || fallback;
};

/**
 * Returns true if WooPayments is in dev mode, false otherwise.
 *
 * @param {boolean} fallback Fallback in case dev mode value can't be found (for example if the wcpaySettings are undefined).
 *
 * @return {boolean} True if in dev mode, false otherwise. Fallback value if dev mode value can't be found.
 */
export const isInDevMode = ( fallback = false ) => {
	if (
		! isObject( wcpaySettings ) ||
		! wcpaySettings.hasOwnProperty( 'devMode' )
	) {
		return fallback;
	}

	return !! wcpaySettings.devMode || fallback;
};

export const getAdminUrl = ( args ) => addQueryArgs( 'admin.php', args );

export { redirectTo } from './navigation';

/**
 * Returns the URL to view a WooPayments document.
 *
 * @param {string} documentId The document ID.
 *
 * @return {string} The URL to view the document.
 *
 * @throws {InvalidPathSegmentError} When the document ID cannot be used in the URL.
 */
export const getDocumentUrl = ( documentId ) => {
	// Remove trailing slash from wpApiSettings.root since NAMESPACE already includes it.
	const baseUrl = `${ wpApiSettings.root.replace( /\/$/, '' ) }`;
	return addQueryArgs(
		`${ baseUrl }${ NAMESPACE }/documents/${ encodePathSegment(
			documentId
		) }`,
		{
			_wpnonce: wpApiSettings.nonce,
		}
	);
};

/**
 * Returns the URL of a WooPayments file, for use as an image source.
 *
 * Carries the REST nonce: without it WordPress treats an image request as logged out. api-fetch's
 * nonce comes first because api-fetch renews it when a request finds it expired.
 *
 * @param {string} fileId The file ID.
 *
 * @return {string} The URL of the file, or an empty string when the ID cannot be used in the URL.
 */
export const getFileUrl = ( fileId ) => {
	let fileSegment;
	try {
		fileSegment = encodePathSegment( fileId );
	} catch ( error ) {
		return '';
	}

	const baseUrl = `${ wpApiSettings.root.replace( /\/$/, '' ) }`;
	return addQueryArgs( `${ baseUrl }${ NAMESPACE }/file/${ fileSegment }`, {
		_wpnonce: apiFetch.nonceMiddleware?.nonce ?? wpApiSettings.nonce,
	} );
};

export const getConnectUrl = ( urlParams, from ) => {
	// Ensure urlParams is an object.
	const queryParams = typeof urlParams === 'object' ? urlParams : {};

	const baseParams = {
		page: 'wc-admin',
		path: '/payments/connect',
		source: queryParams.source?.replace( /[^\w-]+/g, '' ) || 'unknown',
		from: from,
	};

	// Merge queryParams and baseParams into baseParams, ensuring baseParams takes precedence.
	const params = { ...queryParams, ...baseParams };

	return getAdminUrl( params );
};

export const getOverviewUrl = ( urlParams, from ) => {
	// Ensure urlParams is an object.
	const queryParams = typeof urlParams === 'object' ? urlParams : {};

	const baseParams = {
		page: 'wc-admin',
		path: '/payments/overview',
		source: queryParams.source?.replace( /[^\w-]+/g, '' ) || 'unknown',
		from: from,
	};

	// Merge queryParams and baseParams into baseParams, ensuring baseParams takes precedence.
	const params = { ...queryParams, ...baseParams };

	return getAdminUrl( params );
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
 * Resolve an inclusive site-calendar-day filter to its UTC API timestamp.
 *
 * @param {string}  date       Selected calendar date, optionally with a time.
 * @param {boolean} upperBound Whether to include the entire selected day.
 * @return {string} UTC date and time for the server query.
 */
export const formatDateValue = ( date, upperBound = false ) => {
	if ( ! date ) {
		return date;
	}

	const timeZone =
		window.wcSettings?.timeZone ||
		window.wcSettings?.admin?.timeZone ||
		'UTC';
	try {
		const day = PlainDate.fromString( date, getBasic );
		// The last local hour can occur twice. End just before the next day starts.
		const boundaryDay = upperBound ? PlainDate.addDays( day, 1 ) : day;
		const { epochMilliseconds } = PlainDate.toZonedDateTime(
			boundaryDay,
			timeZone
		);
		const bound = moment.utc( epochMilliseconds );
		if ( upperBound ) {
			bound.subtract( 1, 'second' );
		}
		return bound.locale( 'en' ).format( 'YYYY-MM-DD HH:mm:ss' );
	} catch {
		// Keep malformed URL filters in the request so they cannot broaden an export.
		return 'Invalid date';
	}
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
 * Given an object, remove all properties with null or undefined values.
 *
 * @param {Object} obj The object to remove empty properties from.
 * @return {Object|any} A new object with all properties with null or undefined values removed.
 */
export const objectRemoveEmptyProperties = ( obj ) => {
	return Object.keys( obj )
		.filter( ( k ) => obj[ k ] !== null && obj[ k ] !== undefined )
		.reduce( ( a, k ) => ( { ...a, [ k ]: obj[ k ] } ), {} );
};

/**
 * Checks if the passed version is greater than or equal to the base version.
 *
 * Supports semantic version strings like "1.2.3-beta" by ignoring pre-release tags.
 *
 * @param {string} version Version that is compared.
 * @param {string} base    Version to compare with.
 * @return {boolean} Whether version is greater than or equal to base.
 */
export const isVersionGreaterOrEqual = ( version, base ) => {
	const parse = ( v ) => v.split( '-' )[ 0 ].split( '.' ).map( Number );
	const [ v1 = 0, v2 = 0, v3 = 0 ] = parse( version );
	const [ b1 = 0, b2 = 0, b3 = 0 ] = parse( base );
	return (
		v1 > b1 ||
		( v1 === b1 && v2 > b2 ) ||
		( v1 === b1 && v2 === b2 && v3 >= b3 )
	);
};
