/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

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

	// Convert to UTC ISO string for consistent handling
	const utcDateTime = moment.utc( dateTimeStr ).toISOString();

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
	const utcDateTime = moment.unix( timestamp ).utc().toISOString();

	const format =
		customFormat ||
		`${ window.wcpaySettings.dateFormat }${
			includeTime
				? `${ separator }${ window.wcpaySettings.timeFormat }`
				: ''
		}`;

	return dateI18n( format, utcDateTime, timezone );
}
