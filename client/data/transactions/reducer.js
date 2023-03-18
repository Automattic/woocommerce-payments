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
			data: [],
			summary: {
				data: {},
			},
		},
		block: {
			data: [],
			summary: {
				data: {},
			},
		},
	},
};

const receiveTransactions = (
	state = defaultState,
	{ type, query = {}, data = [], error }
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
		case TYPES.SET_BLOCKED_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					block: {
						...state.fraudProtection.block,
						data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_BLOCKED_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					block: {
						...state.fraudProtection.block,
						error,
					},
				},
			};
		case TYPES.SET_ON_REVIEW_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					review: {
						...state.fraudProtection.review,
						data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_ON_REVIEW_TRANSACTIONS:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					review: {
						...state.fraudProtection.review,
						error,
					},
				},
			};
		case TYPES.SET_BLOCKED_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					block: {
						...state.fraudProtection.block,
						summary: {
							...state.fraudProtection.block.summary,
							data,
						},
					},
				},
			};
		case TYPES.SET_ERROR_FOR_BLOCKED_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					block: {
						...state.fraudProtection.block,
						summary: {
							...state.fraudProtection.block.summary,
							error,
						},
					},
				},
			};
		case TYPES.SET_ON_REVIEW_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					review: {
						...state.fraudProtection.review,
						summary: {
							...state.fraudProtection.review.summary,
							data,
						},
					},
				},
			};
		case TYPES.SET_ERROR_FOR_ON_REVIEW_TRANSACTIONS_SUMMARY:
			return {
				...state,
				fraudProtection: {
					...state.fraudProtection,
					review: {
						...state.fraudProtection.review,
						summary: {
							...state.fraudProtection.review.summary,
							error,
						},
					},
				},
			};
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveTransactions;
