/** @format */

export const getReaderStats = ( state, id ) => {
	return state.readers[ id ] && state.readers[ id ].data
		? state.readers[ id ].data
		: {};
};

export const getReaderStatsError = ( state, id ) => {
	return state.readers[ id ] && state.readers[ id ].error
		? state.readers[ id ].error
		: {};
};
