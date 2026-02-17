/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import { __, sprintf } from '@wordpress/i18n';

type DateTimeFormat = string | null;

interface FormatDateTimeOptions {
	/** Whether to include time in the formatted string (defaults to true) */
	includeTime?: boolean;
	/** Separator between date and time (defaults to ' / ') */
	separator?: string;
	/** Custom format to use instead of WordPress settings */
	customFormat?: DateTimeFormat;
	/** Timezone string (e.g., 'UTC', 'America/New_York'). If undefined, uses site default */
	timezone?: string;
}

const msPerMinute = 60_000;
const msPerHour = 3_600_000;
const msPerDay = 86_400_000;

/**
 * Converts a date string to a UTC ISO string. Treats strings without timezone info as UTC.
 */
export function toUtcIsoString( dateTimeStr: string ): string {
	if (
		dateTimeStr.endsWith( 'Z' ) ||
		/[+-]\d{2}:\d{2}$/.test( dateTimeStr )
	) {
		return new Date( dateTimeStr ).toISOString();
	}
	// Replace space with 'T' for ISO-compliant parsing before appending 'Z'.
	const isoStr = dateTimeStr.includes( 'T' )
		? dateTimeStr
		: dateTimeStr.replace( ' ', 'T' );
	return new Date( isoStr + 'Z' ).toISOString();
}

/**
 * Formats a date/time string in YYYY-MM-DD HH:MM:SS format according to WordPress settings.
 * The input date string is converted to UTC for consistent handling across timezones.
 *
 * @param dateTimeStr - Date time string in YYYY-MM-DD HH:MM:SS format
 * @param options - Formatting options
 */
export function formatDateTimeFromString(
	dateTimeStr: string,
	options: FormatDateTimeOptions = {}
): string {
	const {
		customFormat = null,
		includeTime = false,
		separator = ' / ',
		timezone = undefined,
	} = options;

	// Handle numeric timestamps that may be passed as `string` type due to legacy code.
	if ( typeof dateTimeStr === 'number' || /^\d+$/.test( dateTimeStr ) ) {
		const ts =
			typeof dateTimeStr === 'number'
				? dateTimeStr
				: Number( dateTimeStr );
		// Detect if it's milliseconds (> year 5000 in seconds) or seconds
		const utcDateTime =
			ts > 9999999999
				? new Date( ts ).toISOString()
				: new Date( ts * 1000 ).toISOString();

		const format =
			customFormat ||
			`${ window.wcpaySettings.dateFormat }${
				includeTime
					? `${ separator }${ window.wcpaySettings.timeFormat }`
					: ''
			}`;

		return dateI18n( format, utcDateTime, timezone );
	}

	// Convert to UTC ISO string for consistent handling
	const utcDateTime = toUtcIsoString( dateTimeStr );

	const format =
		customFormat ||
		`${ window.wcpaySettings.dateFormat }${
			includeTime
				? `${ separator }${ window.wcpaySettings.timeFormat }`
				: ''
		}`;

	return dateI18n( format, utcDateTime, timezone );
}

/**
 * Formats a Unix timestamp according to WordPress settings.
 * The input timestamp is converted to UTC for consistent handling across timezones.
 *
 * @param timestamp - Unix timestamp (seconds since epoch)
 * @param options - Formatting options
 */
export function formatDateTimeFromTimestamp(
	timestamp: number,
	options: FormatDateTimeOptions = {}
): string {
	const {
		customFormat = null,
		includeTime = false,
		separator = ' / ',
		timezone = undefined,
	} = options;

	// Convert to UTC ISO string for consistent handling
	const utcDateTime = new Date( timestamp * 1000 ).toISOString();

	const format =
		customFormat ||
		`${ window.wcpaySettings.dateFormat }${
			includeTime
				? `${ separator }${ window.wcpaySettings.timeFormat }`
				: ''
		}`;

	return dateI18n( format, utcDateTime, timezone );
}

/**
 * Converts a Unix timestamp (seconds) to a UTC ISO string.
 */
export function unixToUtcIsoString( timestamp: number ): string {
	return new Date( timestamp * 1000 ).toISOString();
}

/**
 * Adds days to a UTC date string and returns an ISO string.
 */
export function addDaysToDate( dateTimeStr: string, days: number ): string {
	const date = new Date( toUtcIsoString( dateTimeStr ) );
	date.setUTCDate( date.getUTCDate() + days );
	return date.toISOString();
}

/**
 * Parses the target date into a Date object.
 */
function parseTargetDate( targetDate: string | Date | number ): Date {
	if ( targetDate instanceof Date ) {
		return targetDate;
	}
	if ( typeof targetDate === 'number' ) {
		return new Date( targetDate * 1000 );
	}
	return new Date( targetDate );
}

/**
 * Returns human-readable relative time string (e.g., "2 days", "an hour").
 * Equivalent to moment's .fromNow(true) -- returns duration without "ago"/"in".
 */
export function getRelativeTimeString(
	targetDate: string | Date | number
): string {
	const target = parseTargetDate( targetDate );
	const diffMs = Math.abs( target.getTime() - Date.now() );

	const seconds = Math.round( diffMs / 1000 );
	const minutes = Math.round( diffMs / msPerMinute );
	const hours = Math.round( diffMs / msPerHour );
	const days = Math.round( diffMs / msPerDay );
	const months = Math.round( days / 30 );
	const years = Math.round( days / 365 );

	if ( seconds < 45 ) return __( 'a few seconds', 'woocommerce-payments' );
	if ( minutes === 1 ) return __( 'a minute', 'woocommerce-payments' );
	if ( minutes < 45 )
		return sprintf(
			/* translators: %d: number of minutes */
			__( '%d minutes', 'woocommerce-payments' ),
			minutes
		);
	if ( hours === 1 ) return __( 'an hour', 'woocommerce-payments' );
	if ( hours < 22 )
		return sprintf(
			/* translators: %d: number of hours */
			__( '%d hours', 'woocommerce-payments' ),
			hours
		);
	if ( days === 1 ) return __( 'a day', 'woocommerce-payments' );
	if ( days < 26 )
		return sprintf(
			/* translators: %d: number of days */
			__( '%d days', 'woocommerce-payments' ),
			days
		);
	if ( months === 1 ) return __( 'a month', 'woocommerce-payments' );
	if ( months < 12 )
		return sprintf(
			/* translators: %d: number of months */
			__( '%d months', 'woocommerce-payments' ),
			months
		);
	if ( years === 1 ) return __( 'a year', 'woocommerce-payments' );
	return sprintf(
		/* translators: %d: number of years */
		__( '%d years', 'woocommerce-payments' ),
		years
	);
}

/**
 * Returns localized weekday name for a given day-of-week index (0=Sunday, 6=Saturday).
 */
export function getWeekdayName( dayIndex: number, locale?: string ): string {
	// Jan 4, 1970 is a known Sunday in UTC
	const date = new Date( Date.UTC( 1970, 0, 4 + dayIndex ) );
	return new Intl.DateTimeFormat( locale || undefined, {
		weekday: 'long',
		timeZone: 'UTC',
	} ).format( date );
}

/**
 * Returns the current timezone offset as a string (e.g., "+05:00", "-03:00").
 */
export function getTimezoneOffsetString(): string {
	const offset = new Date().getTimezoneOffset();
	const sign = offset <= 0 ? '+' : '-';
	const absOffset = Math.abs( offset );
	const hours = String( Math.floor( absOffset / 60 ) ).padStart( 2, '0' );
	const mins = String( absOffset % 60 ).padStart( 2, '0' );
	return `${ sign }${ hours }:${ mins }`;
}

/**
 * Sorts an array of date strings chronologically.
 */
export function sortDatesAsc( dates: string[] ): string[] {
	return [ ...dates ].sort(
		( a, b ) => new Date( a ).getTime() - new Date( b ).getTime()
	);
}

/**
 * Sets time to start-of-day (00:00:00.000) in local time and returns UTC ISO string.
 * This matches moment(date).startOf('day').utc().toISOString() behavior.
 */
export function startOfDayUtc( dateStr: string ): string {
	const date = new Date( dateStr );
	date.setHours( 0, 0, 0, 0 );
	return date.toISOString();
}

/**
 * Sets time to end-of-day (23:59:59.999) in local time and returns UTC ISO string.
 * This matches moment(date).endOf('day').utc().toISOString() behavior.
 */
export function endOfDayUtc( dateStr: string ): string {
	const date = new Date( dateStr );
	date.setHours( 23, 59, 59, 999 );
	return date.toISOString();
}

/**
 * Formats ordinal day (e.g., 1 -> "1st", 2 -> "2nd") with locale awareness.
 */
export function formatOrdinalDay( day: number, locale: string ): string {
	if ( locale.startsWith( 'en' ) ) {
		const suffixes: Record< string, string > = {
			one: 'st',
			two: 'nd',
			few: 'rd',
			other: 'th',
		};
		const pr = new Intl.PluralRules( 'en', { type: 'ordinal' } );
		return `${ day }${ suffixes[ pr.select( day ) ] }`;
	}
	return String( day );
}
