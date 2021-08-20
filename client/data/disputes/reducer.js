/** @format */

/**
 * External dependencies
 */
import { map, keyBy } from 'lodash';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { getResourceId } from 'utils/data';

const defaultState = {
	byId: {},
	queries: {},
	isSavingEvidenceForDispute: {},
	evidenceTransientForDispute: {},
};

const receiveDisputes = (
	state = defaultState,
	{ type, query = {}, data = [] }
) => {
	const index = getResourceId( query );

	switch ( type ) {
		case TYPES.SET_DISPUTE:
			return {
				...state,
				byId: { ...state.byId, [ data.id ]: data },
			};
		case TYPES.SET_DISPUTES:
			return {
				...state,
				byId: { ...state.byId, ...keyBy( data, 'id' ) },
				queries: {
					...state.queries,
					[ index ]: {
						data: map( data, 'id' ),
					},
				},
			};
		case TYPES.SET_IS_SAVING_EVIDENCE_FOR_DISPUTE:
			return {
				...state,
				isSavingEvidenceForDispute: {
					...state.isSavingEvidence,
					[ data.id ]: data.isSavingEvidence,
				},
			};
		case TYPES.SET_EVIDENCE_TRANSIENT_FOR_DISPUTE:
			return {
				...state,
				evidenceTransientForDispute: {
					...state.evidenceTransientForDispute,
					[ data.id ]: data.evidenceTransient,
				},
			};
	}

	return state;
};

export default receiveDisputes;
