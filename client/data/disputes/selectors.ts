/* eslint-disable @typescript-eslint/explicit-module-boundary-types */
/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import type { DisputesState } from './reducer';

export const getDispute = (
	state: { disputes?: DisputesState },
	id: string
) => {
	return state.disputes?.disputes.byId?.[ id ];
};

/**
 * Retrieves the disputes corresponding to the provided query or a sane
 * default if they don't exist.
 *
 * @param {Object} state Current wp.data state.
 * @param {Object} query The disputes query.
 *
 * @return {Object} The list of disputes for the given query.
 */
const getDisputesForQuery = (
	state: { disputes?: DisputesState },
	query: Record< string, string >
) => {
	const index = getResourceId( query );
	return state.disputes?.disputes.queries?.[ index ];
};

export const getDisputes = (
	state: { disputes?: DisputesState },
	query: Record< string, string >
) => {
	const ids = getDisputesForQuery( state, query )?.data ?? [];

	return ids
		.map( ( id ) => getDispute( state, id ) )
		.filter( ( dispute ) => {
			return dispute !== undefined;
		} ) as Dispute[];
};

export const getIsSavingEvidenceForDispute = (
	state: { disputes?: DisputesState },
	id: string
) => {
	return (
		state.disputes?.savingEvidenceStatus?.isSavingEvidenceForDispute?.[
			id
		] ?? false
	);
};

export const getEvidenceTransientForDispute = (
	state: { disputes?: DisputesState },
	disputeId: string
) => {
	return (
		state.disputes?.evidence.evidenceTransientForDispute?.[ disputeId ] ??
		{}
	);
};

export const getIsUploadingEvidenceForDispute = (
	state: { disputes?: DisputesState },
	disputeId: string
) => {
	return (
		state.disputes?.evidenceUploadStatus.isUploadingEvidenceForDispute?.[
			disputeId
		] ?? {}
	);
};

export const getEvidenceUploadErrorsForDispute = (
	state: { disputes?: DisputesState },
	disputeId: string
) => {
	return (
		state.disputes?.evidenceUploadErrors.evidenceUploadErrorsForDispute?.[
			disputeId
		] ?? {}
	);
};
