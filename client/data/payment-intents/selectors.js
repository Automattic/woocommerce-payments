/** @format */

export const getPaymentIntent = ( state, id ) => {
	return state.paymentIntents[ id ] && state.paymentIntents[ id ].data
		? state.paymentIntents[ id ].data
		: {};
};

export const getPaymentIntentError = ( state, id ) => {
	return state.paymentIntents[ id ] && state.paymentIntents[ id ].error
		? state.paymentIntents[ id ].error
		: {};
};
