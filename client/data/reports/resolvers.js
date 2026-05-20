/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateErrorForReportsFees,
	updateErrorForReportsFeesSummary,
	updateReportsFees,
	updateReportsFeesSummary,
} from './actions';
import { formatDateValue, getUserTimeZone } from 'utils';

export const formatReportsFeesQuery = ( query ) => ( {
	match: query.match,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	payment_method_type: query.paymentMethodType,
	type: query.type,
	order_id: query.orderId,
	deposit_id: query.depositId,
	customer_email: query.customerEmail,
	search: query.search,
	user_timezone: getUserTimeZone(),
} );

const getReportsFeesRows = ( results ) => {
	if ( Array.isArray( results ) ) {
		return results;
	}

	return results?.data || [];
};

/**
 * Retrieves Fees report rows.
 *
 * @param {Object} query Data on which to parameterize the selection.
 */
export function* getReportsFees( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/reports/fees`, {
		page: query.paged,
		per_page: query.perPage,
		sort: query.orderby,
		direction: query.order,
		...formatReportsFeesQuery( query ),
	} );

	try {
		const results = yield apiFetch( { path } );
		yield updateReportsFees( query, getReportsFeesRows( results ) );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving fees report.', 'woocommerce-payments' )
		);
		yield updateErrorForReportsFees( query, null, e );
	}
}

/**
 * Retrieves Fees report summary data.
 *
 * @param {Object} query Data on which to parameterize the selection.
 */
export function* getReportsFeesSummary( query ) {
	const path = addQueryArgs(
		`${ NAMESPACE }/reports/fees/summary`,
		formatReportsFeesQuery( query )
	);

	try {
		const summary = yield apiFetch( { path } );
		yield updateReportsFeesSummary( query, summary );
	} catch ( e ) {
		yield controls.dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving fees report summary.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForReportsFeesSummary( query, null, e );
	}
}
