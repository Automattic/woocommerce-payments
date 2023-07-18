/**
 * External dependencies declarations
 */

declare module '@wordpress/date' {
	export function dateI18n(
		dateFormat: string,
		dateValue: import('moment').Moment | Date | string | undefined,
		timezone?: string | boolean
	): string;
}

declare module 'dompurify' {
	/* eslint-disable @typescript-eslint/naming-convention */
	export function sanitize(
		html: string,
		options?: {
			ALLOWED_TAGS: string[];
			ALLOWED_ATTR: string[];
		}
	): string;
	/* eslint-enable @typescript-eslint/naming-convention */
}

declare module '@woocommerce/settings' {
	const getSetting: ( key: string ) => string;
}
