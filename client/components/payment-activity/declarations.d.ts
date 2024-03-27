/**
 * External dependencies
 */
import { Moment } from '@wordpress/date/build-types';

/**
 * The types for the `@woocommerce/components` package are being declared here
 * because it does not have its own types (as of version 5.1.2).
 * We should remove this file once we've updated to a version of `@woocommerce/components` with type defs.
 */
declare module '@woocommerce/components' {
	const DateRange: ( props: {
		after: Moment;
		before: Moment;
		afterText: string;
		beforeText: string;
		onUpdate: ( data: any ) => any;
		shortDateFormat: string;
		focusedInput: string;
		isInvalidDate: ( date: any ) => any;
	} ) => JSX.Element;
}
