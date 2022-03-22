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
	updateErrorForLoans,
	updateLoans,
} from './actions';
import { ApiError, Summary, LoansList } from './types';

/**
 * Retrieve the summary data for the currently active loan.
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

/**
 * Retrieve all the past and present capital loans.
 */
export function* getLoans(): unknown {
	const path = `${ NAMESPACE }/capital/loans`;

	try {
		const result = yield apiFetch( { path } );
		yield updateLoans( result as LoansList );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__(
				'Error retrieving the active loan summary.',
				'woocommerce-payments'
			)
		);
		yield updateErrorForLoans( e as ApiError );
	}
}
