/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';
import {
	UpdateAuthorizationAction,
	UpdateAuthorizationsAction,
	UpdateAuthorizationsSummaryAction,
} from './types';
import { Authorization } from 'wcpay/types/authorizations';

const defaultState = { summary: {}, byId: {} };
// TODO remove any(s).

const receiveAuthorizations = (
	state = defaultState,
	action:
		| UpdateAuthorizationAction
		| UpdateAuthorizationsAction
		| UpdateAuthorizationsSummaryAction
): any => {
	switch ( action.type ) {
		case TYPES.SET_AUTHORIZATION:
			const authorization = action.data as Authorization;
			return {
				...state,
				byId: {
					...state.byId,
					[ authorization.authorization_id ]: authorization,
				},
			};
		case TYPES.SET_AUTHORIZATIONS:
			return {
				...state,
				[ getResourceId( action.query ) ]: {
					data: action.data,
				},
			};
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS:
			return {
				...state,
				[ getResourceId( action.query ) ]: {
					error: action.error,
				},
			};
		case TYPES.SET_AUTHORIZATIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ getResourceId( action.query ) ]: {
						data: action.data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ getResourceId( action.query ) ]: {
						error: action.error,
					},
				},
			};
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveAuthorizations;
