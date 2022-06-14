/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTimeline = ( id ) =>
	useSelect(
		( select ) => {
			const {
				getTimeline,
				getTimelineError,
				isResolving,
				getPaymentIntent,
				getPaymentIntentError,
			} = select( STORE_NAME );

			// Make sure the charge is loaded first to get the intention ID.
			const isLoading = isResolving( 'getPaymentIntent', [ id ] );
			const error = getPaymentIntentError( id );

			if ( isLoading || error instanceof Error ) {
				return {
					timeline: null,
					timelineError: error,
					isLoading: isLoading,
				};
			}

			const { id: intentionId } = getPaymentIntent( id );
			if ( ! intentionId ) {
				// If intention ID is not available, do not render the timeline, but also don't indicate the API error.
				return {
					timeline: null,
					timelineError: null,
					isLoading: false,
				};
			}

			return {
				timeline: getTimeline( intentionId ),
				timelineError: getTimelineError( intentionId ),
				isLoading: isResolving( 'getTimeline', [ intentionId ] ),
			};
		},
		[ id ]
	);
