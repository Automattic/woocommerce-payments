/** @format */

/**
 * External dependencies
 */
import { useCallback } from 'react';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './store';
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
	const {
		dismissDisputeReadinessCard,
		confirmStatementDescriptor,
		invalidateResolutionForStoreSelector,
	} = useDispatch( STORE_NAME );
	const refreshDisputeReadiness = useCallback( () => {
		invalidateResolutionForStoreSelector( 'getDisputeReadiness' );
	}, [ invalidateResolutionForStoreSelector ] );

	return {
		dismissDisputeReadinessCard,
		confirmStatementDescriptor,
		refreshDisputeReadiness,
	};
};
