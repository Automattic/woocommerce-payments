/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTimeline = ( paymentIntentId ) =>
	useSelect(
		( select ) => {
			const { getTimeline, getTimelineError, isResolving } = select(
				STORE_NAME
			);

			return {
				timeline: getTimeline( paymentIntentId ),
				timelineError: getTimelineError( paymentIntentId ),
				isLoading: isResolving( 'getTimeline', [ paymentIntentId ] ),
			};
		},
		[ paymentIntentId ]
	);
