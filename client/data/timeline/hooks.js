/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTimeline = ( chargeId ) =>
	useSelect(
		( select ) => {
			const {
				getTimeline,
				getTimelineError,
				isResolving,
				getCharge,
				getChargeError,
			} = select( STORE_NAME );

			// Make sure the charge is loaded first to get the intention ID.
			const isChargeLoading = isResolving( 'getCharge', [ chargeId ] );
			const chargeError = getChargeError( chargeId );
			if ( isChargeLoading || chargeError instanceof Error ) {
				return {
					timeline: null,
					timelineError: chargeError,
					isLoading: isChargeLoading,
				};
			}

			const { payment_intent: intentionId } = getCharge( chargeId );

			return {
				timeline: getTimeline( intentionId ),
				timelineError: getTimelineError( intentionId ),
				isLoading: isResolving( 'getTimeline', [ intentionId ] ),
			};
		},
		[ chargeId ]
	);
