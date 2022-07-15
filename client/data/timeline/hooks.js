/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTimeline = ( paymentIntentId ) =>
	useSelect(
		( select ) => {
			const {
				getTimeline,
				getTimelineError,
				isResolving,
				getPaymentIntentError,
			} = select( STORE_NAME );

			// Make sure the charge is loaded first to get the intention ID.
			const isLoading = isResolving( 'getPaymentIntent', [
				paymentIntentId,
			] );
			const error = getPaymentIntentError( paymentIntentId );

			if ( isLoading || error instanceof Error ) {
				return {
					timeline: null,
					timelineError: error,
					isLoading: isLoading,
				};
			}

			return {
				timeline: getTimeline( paymentIntentId ),
				timelineError: getTimelineError( paymentIntentId ),
				isLoading: isResolving( 'getTimeline', [ paymentIntentId ] ),
			};
		},
		[ paymentIntentId ]
	);
