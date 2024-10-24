/**
 * External dependencies
 */
import { dateI18n } from '@wordpress/date';

type DateTimeFormat = string | null;

interface FormatDateTimeOptions {
	includeTime?: boolean; // Whether to include time in the formatted string (defaults to true)
	useGmt?: boolean; // Whether to display the time in GMT/UTC (defaults to false)
}

/**
 * Formats a date and time string according to WordPress settings or a custom format.
 *
 * @param { string } dateTime - The date and time string from the database in UTC (e.g., '2024-10-23 15:28:26').
 * @param { DateTimeFormat } customFormat - Optional custom format for date and time. If not provided, uses WordPress settings.
 * @param { FormatDateTimeOptions } options - Additional options to control time inclusion and whether to use GMT/UTC.
 * @return { string } - The formatted date and time string.
 */
export function formatDateTime(
	dateTime: string,
	customFormat: DateTimeFormat = null,
	options: FormatDateTimeOptions = { includeTime: true, useGmt: true }
): string {
	const { includeTime = true, useGmt = true } = options;

	// Use the WordPress settings for date and time format if no custom format is provided
	const dateFormat = customFormat || window.wcpaySettings.dateFormat;
	const timeFormat = includeTime ? window.wcpaySettings.timeFormat : '';
	const format = `${ dateFormat }${
		includeTime ? ` / ${ timeFormat }` : ''
	}`;

	return dateI18n( format, dateTime, useGmt );
}
