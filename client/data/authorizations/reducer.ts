/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';
import {
	AuthorizationsState,
	SetErrorForAuthorizationsAction,
	SetErrorForAuthorizationsSummaryAction,
	SetIsRequestingAuthorizationsAction,
	UpdateAuthorizationAction,
	UpdateAuthorizationsAction,
	UpdateAuthorizationsSummaryAction,
} from 'wcpay/types/authorizations';

const defaultState = { summary: {}, byId: {}, isRequesting: false };

const receiveAuthorizations = (
	state: AuthorizationsState = defaultState,
	action:
		| UpdateAuthorizationAction
		| UpdateAuthorizationsAction
		| UpdateAuthorizationsSummaryAction
		| SetErrorForAuthorizationsAction
		| SetErrorForAuthorizationsSummaryAction
		| SetIsRequestingAuthorizationsAction
): AuthorizationsState => {
	switch ( action.type ) {
		case TYPES.SET_AUTHORIZATION: {
			const { data } = action as UpdateAuthorizationAction;

			return {
				...state,
				byId: {
					...state.byId,
					[ data.payment_intent_id ]: {
						...state.byId[ data.payment_intent_id ],
						...data,
					},
				},
			};
		}
		case TYPES.SET_AUTHORIZATIONS: {
			const { data, query } = action as UpdateAuthorizationsAction;

			return {
				...state,
				[ getResourceId( query ) ]: { data },
			};
		}
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS: {
			const { error, query } = action as SetErrorForAuthorizationsAction;

			return {
				...state,
				[ getResourceId( query ) ]: {
					error: error,
				},
			};
		}
		case TYPES.SET_AUTHORIZATIONS_SUMMARY: {
			const { data, query } = action as UpdateAuthorizationsSummaryAction;

			return {
				...state,
				summary: {
					...state.summary,
					[ getResourceId( query ) ]: {
						data: data || {},
					},
				},
			};
		}
		case TYPES.SET_ERROR_FOR_AUTHORIZATIONS_SUMMARY: {
			const {
				query,
				error,
			} = action as SetErrorForAuthorizationsSummaryAction;

			return {
				...state,
				summary: {
					...state.summary,
					[ getResourceId( query ) ]: {
						error: error || '',
					},
				},
			};
		}
		case TYPES.SET_IS_REQUESTING_AUTHORIZATION: {
			const { data } = action as SetIsRequestingAuthorizationsAction;

			return {
				...state,
				isRequesting: data,
			};
		}
	}

	// Fallback to returning the same state.
	return state;
};

export default receiveAuthorizations;
