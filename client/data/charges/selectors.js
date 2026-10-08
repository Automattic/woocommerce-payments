/** @format */

// Shared fallback so `useSelect` gets a stable reference while nothing is loaded.
const EMPTY_OBJECT = {};

export const getCharge = ( state, id ) => {
	return state.charges[ id ] && state.charges[ id ].data
		? state.charges[ id ].data
		: EMPTY_OBJECT;
};

export const getChargeError = ( state, id ) => {
	return state.charges[ id ] && state.charges[ id ].error
		? state.charges[ id ].error
		: EMPTY_OBJECT;
};

export const getChargeFromOrder = ( state, id ) => getCharge( state, id );

export const getChargeFromOrderError = ( state, id ) =>
	getChargeError( state, id );
