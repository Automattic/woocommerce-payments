/** @format */

// Shared fallback so `useSelect` gets a stable reference while nothing is loaded.
const EMPTY_OBJECT = {};

export const getTimeline = ( state, id ) => {
	return state.timeline && state.timeline[ id ] && state.timeline[ id ].data
		? state.timeline[ id ].data
		: EMPTY_OBJECT;
};

export const getTimelineError = ( state, id ) => {
	return state.timeline && state.timeline[ id ] && state.timeline[ id ].error
		? state.timeline[ id ].error
		: EMPTY_OBJECT;
};
