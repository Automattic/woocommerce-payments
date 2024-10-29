/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';

type DateTimeFormat = string | null;

interface FormatDateTimeOptions {
	includeTime?: boolean; // Whether to include time in the formatted string (defaults to true)
	useGmt?: boolean; // Whether to display the time in GMT/UTC (defaults to false)
	separator?: string; // Separator between date and time (defaults to '/')
	customFormat?: DateTimeFormat; // Custom format to use instead of WordPress settings
}

/**
 * Formats a date and time string according to WordPress settings or a custom format.
 *
 * @param { string } dateTime - The date and time string or date from the database in UTC (e.g., '2024-10-23 15:28:26').
 * @param { FormatDateTimeOptions } options - Additional options to control time inclusion and whether to use GMT/UTC.
 * @return { string } - The formatted date and time string.
 */
export function formatUserDateTime(
	dateTime: string | Date,
	options: FormatDateTimeOptions = {
		includeTime: true,
		useGmt: true,
		separator: ' / ',
		customFormat: null,
	}
): string {
	const {
		customFormat = null,
		includeTime = true,
		useGmt = true,
		separator = ' / ',
	} = options;

	// Use the WordPress settings for date and time format if no custom format is provided
	const format =
		customFormat ||
		`${ window.wcpaySettings.dateFormat }${
			includeTime
				? `${ separator }${ window.wcpaySettings.timeFormat }`
				: ''
		}`;

	return dateI18n( format, dateTime, useGmt );
}
