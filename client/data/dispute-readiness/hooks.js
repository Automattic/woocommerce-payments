/** @format */

/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

export const useDisputeReadiness = () =>
	useSelect( ( select ) => {
		const { getDisputeReadiness, getDisputeReadinessError, isResolving } =
			select( STORE_NAME );

		return {
			disputeReadiness: getDisputeReadiness(),
			disputeReadinessError: getDisputeReadinessError(),
			isLoading: isResolving( 'getDisputeReadiness' ),
		};
	} );

export const useDisputeReadinessActions = () => {
	const { dismissDisputeReadinessCard } = useDispatch( STORE_NAME );

	return { dismissDisputeReadinessCard };
};
