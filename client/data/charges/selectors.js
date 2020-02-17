/** @format */

export const getCharge = ( state, id ) => {
	return state.charges[ id ] && state.charges[ id ].data ? state.charges[ id ].data : {};
};

export const getChargeError = ( state, id ) => {
	return state.charges[ id ] && state.charges[ id ].error ? state.charges[ id ].error : {};
};
