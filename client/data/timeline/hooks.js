/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useTimeline = ( intentionId ) => useSelect( select => {
	const { getTimeline, getTimelineError, isResolving } = select( STORE_NAME );
	return {
		timeline: getTimeline( intentionId ),
		timelineError: getTimelineError( intentionId ),
		isLoading: isResolving( 'getTimeline', [ intentionId ] ),
	};
}, [ intentionId ] );
