/**
 * External dependencies
 */
import '@wordpress/date';

declare module '@wordpress/date' {
	export function dateI18n(
		dateFormat: string,
		dateValue: import('moment').Moment | Date | string | undefined,
		timezone?: string | boolean
	): string;
}
