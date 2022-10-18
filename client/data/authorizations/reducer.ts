/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';
import { Query } from '@woocommerce/navigation';
import { Authorization } from 'wcpay/types/authorizations';

const defaultState = { summary: {} };

const receiveAuthorizations = (
	state = defaultState,
	{
		type,
		query = {},
		data = [],
		error,
	}: {
		type: string;
		query: Query;
		data: Authorization[];
		error: string;
	}
): Record< string, any > => {
	const index = getResourceId(query);

	switch ( type ) {
		case TYPES.SET_AUTHORIZATIONS:
			return {
				...state,
				[ index ]: {
					data: data,
				},
			};
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS:
			return {
				...state,
				[ index ]: {
					error: error,
				},
			};
		case TYPES.SET_AUTHORIZATIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data: data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						error: error,
					},
				},
			};
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveAuthorizations;
