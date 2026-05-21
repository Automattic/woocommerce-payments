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

// The Fees REST schema declares `type` as an array (see Reports controller).
// URL helpers and DataViews can hand us a scalar string, so normalize at the
// resolver boundary — sending a string would fall back on WP REST's lenient
// coercion and may be rejected as the schema tightens.
const toArrayOrUndefined = ( value ) => {
	if ( value === undefined || value === null || value === '' ) {
		return undefined;
	}
	return Array.isArray( value ) ? value : [ value ];
};

const formatQueryFilters = ( query ) => ( {
	match: query.match,
	date_before: formatDateValue( query.dateBefore, true ),
	date_after: formatDateValue( query.dateAfter ),
	date_between: query.dateBetween && [
		formatDateValue( query.dateBetween[ 0 ] ),
		formatDateValue( query.dateBetween[ 1 ], true ),
	],
	payment_method_type: query.paymentMethodType,
	type: toArrayOrUndefined( query.type ),
	order_id: query.orderId,
	deposit_id: query.depositId,
	// Keep customer email out of GET query strings; the Fees UI does not expose
	// this filter, and URLs can be persisted in logs/history.
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
		...formatQueryFilters( query ),
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
		yield updateErrorForReportsFees( query, e );
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
		formatQueryFilters( query )
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
		yield updateErrorForReportsFeesSummary( query, e );
	}
}
