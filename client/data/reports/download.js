/** @format */

/**
 * External dependencies
 */
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import { formatQueryFilters } from './resolvers';

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
