/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { LatestFraudOutcomeResponse } from './types';

export const useLatestFraudOutcome = (
	id: string
): LatestFraudOutcomeResponse =>
	useSelect(
		( select ) => {
			const {
				isResolving,
				getLatestFraudOutcome,
				getLatestFraudOutcomeError,
			} = select( STORE_NAME );

			return {
				data: getLatestFraudOutcome( id ),
				error: getLatestFraudOutcomeError( id ),
				isLoading: isResolving( 'getLatestFraudOutcome', [ id ] ),
			};
		},
		[ id ]
	);
