/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';
import moment from 'moment';

type DateTimeFormat = string | null;

interface FormatDateTimeOptions {
	includeTime?: boolean; // Whether to include time in the formatted string (defaults to false)
	separator?: string; // Separator between date and time (defaults to ' / ')
	customFormat?: DateTimeFormat; // Custom format to use instead of WordPress settings
}

/**
 * Formats a date/time string in YYYY-MM-DD HH:MM:SS format according to WordPress settings.
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

	return dateI18n( format, utcDateTime );
}

/**
 * Formats a Unix timestamp according to WordPress settings.
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

	return dateI18n( format, utcDateTime );
}
