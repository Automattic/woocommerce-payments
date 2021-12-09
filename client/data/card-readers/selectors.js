/** @format */

export const getCardReaderStats = ( state, id ) => {
	return state.readers[ id ] && state.readers[ id ].data
		? state.readers[ id ].data
		: {};
};

export const getCardReaderStatsError = ( state, id ) => {
	return state.readers[ id ] && state.readers[ id ].error
		? state.readers[ id ].error
		: {};
};

export const getCardReaders = ( state ) => {
	return state.readers && state.readers.list ? state.readers.list : {};
};
