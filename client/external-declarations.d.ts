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
	const DOMPurify: {
		sanitize(
			html: string,
			options?: {
				ALLOWED_TAGS: string[];
				ALLOWED_ATTR: string[];
			}
		): string;
	};
	export default DOMPurify;
	/* eslint-enable @typescript-eslint/naming-convention */
}
