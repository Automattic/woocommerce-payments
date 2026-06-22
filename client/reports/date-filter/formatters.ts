/** @format */

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { formatDateTimeFromString } from 'wcpay/utils/date-time';
import type { DateFilterValue, DateOperator } from './types';

const formatDate = ( ymd: string ): string =>
	formatDateTimeFromString( ymd, { includeTime: false } );

export const operatorLabel = ( operator: DateOperator ): string => {
	switch ( operator ) {
		case 'on':
			return __( 'On', 'woocommerce-payments' );
		case 'before':
			return __( 'Before', 'woocommerce-payments' );
		case 'after':
			return __( 'After', 'woocommerce-payments' );
		case 'between':
			return __( 'Between', 'woocommerce-payments' );
	}
};

/**
 * Return the closed-chip summary for a DateFilterValue, e.g. "On: 03/05/2026"
 * or "Between (inc): 01/05/2026 and 18/05/2026". Locale-aware via
 * formatDateTimeFromString.
 */
export const formatDateFilterSummary = ( value: DateFilterValue ): string => {
	if ( value.operator === 'between' ) {
		return sprintf(
			/* translators: 1: range start date, 2: range end date. */
			__( '%1$s and %2$s', 'woocommerce-payments' ),
			formatDate( value.value[ 0 ] ),
			formatDate( value.value[ 1 ] )
		);
	}
	return formatDate( value.value );
};

/**
 * Combined "<operator>: <summary>" string used for the chip label and for
 * screen-reader announcements.
 */
export const formatDateFilterChipLabel = ( value: DateFilterValue ): string => {
	return sprintf(
		/* translators: 1: operator name (On/Before/After/Between (inc)), 2: formatted date(s). */
		__( '%1$s: %2$s', 'woocommerce-payments' ),
		operatorLabel( value.operator ),
		formatDateFilterSummary( value )
	);
};
