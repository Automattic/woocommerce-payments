/** @format */

/**
 * Internal dependencies
 */
import { TimelineState } from './reducer';
import { Timeline } from './types';

export const getTimeline = (
	state: { timeline: TimelineState },
	id: string
): Timeline | undefined => {
	return state.timeline?.data?.[ id ];
};

export const getTimelineError = (
	state: { timeline: TimelineState },
	id: string
): Error | undefined => {
	return state.timeline?.errors?.[ id ];
};
