/** @format */

/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import { DisputeReadinessActions, DisputeReadinessResponse } from './types';

export const useDisputeReadiness = (): DisputeReadinessResponse =>
	useSelect( ( select ) => {
		const { getDisputeReadiness, getDisputeReadinessError, isResolving } =
			select( STORE_NAME );

		return {
			disputeReadiness: getDisputeReadiness(),
			disputeReadinessError: getDisputeReadinessError(),
			isLoading: isResolving( 'getDisputeReadiness' ),
		};
	} );

export const useDisputeReadinessActions = (): DisputeReadinessActions => {
	const { dismissDisputeReadinessCard } = useDispatch( STORE_NAME );

	return { dismissDisputeReadinessCard };
};
