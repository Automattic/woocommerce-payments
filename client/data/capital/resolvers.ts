/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE } from '../constants';
import {
	updateActiveLoanSummary,
	updateErrorForActiveLoanSummary,
} from './actions';
import { ApiError, Summary } from './types';

/**
 * Retrieve all deposits' overviews from the deposits API.
 */
export function* getActiveLoanSummary(): unknown {
	const path = `${ NAMESPACE }/capital/active_loan_summary`;

	try {
		const result = yield apiFetch( { path } );
		yield updateActiveLoanSummary( result as Summary );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving the active loan summary.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForActiveLoanSummary( e as ApiError );
	}
}
