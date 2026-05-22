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
 * REST endpoint for requesting and polling Fees CSV exports.
 *
 * POST without a path segment to start the export (returns an export ID).
 * GET with `/{export_id}` appended to poll for the signed download URL.
 */
export const feesDownloadEndpoint = `${ NAMESPACE }/reports/fees/download`;

/**
 * Builds the POST URL that kicks off a Fees CSV export.
 *
 * Accepts the same query shape as the Fees list/summary resolvers so the
 * export honours the same filters as the on-screen table. Adds `user_email`
 * and `locale` — both required by the backend export pipeline but scoped to
 * this helper so they never leak into list/summary GET URLs (and from there
 * into access logs).
 *
 * @param {Object} query Fees report query.
 * @return {string} Fully-qualified REST path including serialized query string.
 */
export function getFeesCSVRequestURL( query ) {
	return addQueryArgs( feesDownloadEndpoint, {
		...formatQueryFilters( query ),
		user_email: query.userEmail,
		locale: query.locale,
	} );
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
