/** @format */

export const getTimeline = ( state, id ) => {
	return state.timeline && state.timeline[ id ] && state.timeline[ id ].data ? state.timeline[ id ].data : {};
};

export const getTimelineError = ( state, id ) => {
	return state.timeline && state.timeline[ id ] && state.timeline[ id ].error ? state.timeline[ id ].error : {};
};
