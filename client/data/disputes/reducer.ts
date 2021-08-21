/* eslint-disable @typescript-eslint/explicit-module-boundary-types */
/** @format */

/**
 * External dependencies
 */
import { Reducer } from 'redux';
import { combineReducers } from '@wordpress/data';
import { map, keyBy } from 'lodash';

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import type {
	DisputesAction,
	EvidenceAction,
	EvidenceUploadErrorAction,
	EvidenceUploadStatusAction,
	SavingEvidenceStatusAction,
} from './actions';

type DisputesListState = {
	byId: Record< string, Dispute >;
	queries: { [ key: string ]: { data: string[] } };
};
type IsSavingEvidenceState = {
	isSavingEvidenceForDispute: Record< string, boolean >;
};
type IsUploadingEvidenceState = {
	isUploadingEvidenceForDispute: {
		[ disputeId: string ]: { [ key: string ]: boolean };
	};
};
type EvidenceUploadErrorsState = {
	evidenceUploadErrorsForDispute: {
		[ disputeId: string ]: { [ key: string ]: string };
	};
};
type EvidenceState = {
	evidenceTransientForDispute: {
		[ disputeId: string ]: Partial< Evidence >;
	};
};

const receiveDisputes: Reducer< DisputesListState, DisputesAction > = (
	state = { byId: {}, queries: {} },
	{ type, query = {}, data = [] }
) => {
	const index = getResourceId( query );

	switch ( type ) {
		case 'SET_DISPUTES':
			const disputes = data as Dispute[];
			return {
				...state,
				byId: { ...state.byId, ...keyBy( data, 'id' ) },
				queries: {
					...state.queries,
					[ index ]: {
						data: map( disputes, 'id' ),
					},
				},
			};
		case 'SET_DISPUTE':
			const dispute = data as Dispute;
			return {
				...state,
				byId: { ...state.byId, [ dispute.id ]: dispute },
			};
	}

	return state;
};

const receiveSavingEvidenceStatus: Reducer<
	IsSavingEvidenceState,
	SavingEvidenceStatusAction
> = ( state = { isSavingEvidenceForDispute: {} }, { type, data } ) => {
	switch ( type ) {
		case 'SET_IS_SAVING_EVIDENCE_FOR_DISPUTE':
			return {
				...state,
				isSavingEvidenceForDispute: {
					...state.isSavingEvidenceForDispute,
					[ data.id ]: data.isSavingEvidence,
				},
			};
	}

	return state;
};

const receiveEvidenceUploadStatus: Reducer<
	IsUploadingEvidenceState,
	EvidenceUploadStatusAction
> = ( state = { isUploadingEvidenceForDispute: {} }, { type, data } ) => {
	switch ( type ) {
		case 'SET_IS_UPLOADING_EVIDENCE_FOR_DISPUTE':
			return {
				...state,
				isUploadingEvidenceForDispute: {
					...state.isUploadingEvidenceForDispute,
					[ data.id ]: {
						[ data.key ]: data.isUploadingEvidenceForDispute,
					},
				},
			};
	}

	return state;
};

const receiveEvidenceUploadErrors: Reducer<
	EvidenceUploadErrorsState,
	EvidenceUploadErrorAction
> = ( state = { evidenceUploadErrorsForDispute: {} }, { type, data } ) => {
	switch ( type ) {
		case 'SET_EVIDENCE_UPLOAD_ERRORS_FOR_DISPUTE':
			return {
				...state,
				evidenceUploadErrorsForDispute: {
					...state.evidenceUploadErrorsForDispute,
					[ data.id ]: {
						[ data.key ]: data.errorMessage,
					},
				},
			};
	}

	return state;
};

const receiveEvidence: Reducer< EvidenceState, EvidenceAction > = (
	state = { evidenceTransientForDispute: {} },
	{ type, data }
) => {
	switch ( type ) {
		case 'SET_EVIDENCE_TRANSIENT_FOR_DISPUTE':
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

const reducer = combineReducers( {
	disputes: receiveDisputes,
	evidence: receiveEvidence,
	evidenceUploadStatus: receiveEvidenceUploadStatus,
	savingEvidenceStatus: receiveSavingEvidenceStatus,
	evidenceUploadErrors: receiveEvidenceUploadErrors,
} );

export type DisputesState = ReturnType< typeof reducer >;

export default reducer;
