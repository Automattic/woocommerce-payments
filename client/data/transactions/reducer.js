/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';

const defaultState = {
	summary: {},
	fraudProtection: {
		review: {
			summary: {},
		},
		block: {
			summary: {},
		},
	},
};

const receiveTransactions = (
	state = defaultState,
	{ type, query = {}, data = [], error, status }
) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_TRANSACTIONS:
			return {
				...state,
				[ index ]: {
					data: data,
				},
			};
		case TYPES.SET_ERROR_FOR_TRANSACTIONS:
			return {
				...state,
				[ index ]: {
					error: error,
				},
			};
		case TYPES.SET_TRANSACTIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data: data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_TRANSACTIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						error: error,
					},
				},
			};
		case TYPES.SET_FRAUD_OUTCOME_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					[ status ]: {
						...( state.fraudProtection?.[ status ] || {} ),
						[ index ]: {
							data,
						},
					},
				},
			};
		case TYPES.SET_ERROR_FOR_FRAUD_OUTCOME_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					[ status ]: {
						...( state.fraudProtection?.[ status ] || {} ),
						[ index ]: {
							error,
						},
					},
				},
			};
		case TYPES.SET_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					[ status ]: {
						...( state.fraudProtection?.[ status ] || {} ),
						summary: {
							...( state.fraudProtection?.[ status ]?.summary ||
								{} ),
							[ index ]: {
								data,
							},
						},
					},
				},
			};
		case TYPES.SET_ERROR_FOR_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					[ status ]: {
						...( state.fraudProtection?.[ status ] || {} ),
						summary: {
							...( state.fraudProtection?.[ status ]?.summary ||
								{} ),
							[ index ]: {
								error,
							},
						},
					},
				},
			};
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveTransactions;
