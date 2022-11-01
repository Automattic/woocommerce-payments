/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';
import {
	Authorization,
	AuthorizationsState,
	AuthorizationsSummary,
	UpdateAuthorizationAction,
	UpdateAuthorizationsAction,
	UpdateAuthorizationsSummaryAction,
} from 'wcpay/types/authorizations';

const defaultState = { summary: {} };

const receiveAuthorizations = (
	state: AuthorizationsState = defaultState,
	action:
		| UpdateAuthorizationAction
		| UpdateAuthorizationsAction
		| UpdateAuthorizationsSummaryAction
): Record< string, any > => {
	switch ( action.type ) {
		case TYPES.SET_AUTHORIZATIONS:
			return {
				...state,
				[ getResourceId( action.query ) ]: {
					data: action.data as Authorization,
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
						data: action.data as AuthorizationsSummary,
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
