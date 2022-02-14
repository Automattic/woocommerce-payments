/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { SummaryResponse } from './types';

export const useActiveLoanSummary = (): SummaryResponse =>
	useSelect( ( select ) => {
		const {
			getActiveLoanSummary,
			getActiveLoanSummaryError,
			isResolving,
		} = select( STORE_NAME );

		return {
			summary: getActiveLoanSummary(),
			summaryError: getActiveLoanSummaryError(),
			isLoading: isResolving( 'getActiveLoanSummary' ),
		};
	} );
