/** @format */

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';

const defaultState = {
	summary: {},
};

const receiveReports = (
	state = defaultState,
	{ type, query = {}, data = [], error }
) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_REPORTS_FEES:
			return {
				...state,
				[ index ]: {
					data,
				},
			};
		case TYPES.SET_ERROR_FOR_REPORTS_FEES:
			return {
				...state,
				[ index ]: {
					error,
				},
			};
		case TYPES.SET_REPORTS_FEES_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						data,
					},
				},
			};
		case TYPES.SET_ERROR_FOR_REPORTS_FEES_SUMMARY:
			return {
				...state,
				summary: {
					...state.summary,
					[ index ]: {
						error,
					},
				},
			};
	}

	return state;
};

export default receiveReports;
