/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useDispute = ( id ) => {
	const { dispute, isLoading } = useSelect(
		( select ) => {
			const { getDispute, isResolving } = select( STORE_NAME );

			return {
				dispute: getDispute( id ),
				isLoading: isResolving( 'getDispute', [ id ] ),
			};
		},
		[ id ]
	);

	const { acceptDispute, updateDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( id );

	return {
		dispute,
		isLoading,
		doAccept,
		updateDispute,
	};
};

export const useDisputeEvidence = ( disputeId ) => {
	const {
		isSavingEvidence,
		isUploadingEvidence,
		evidenceTransient,
		evidenceUploadErrors,
	} = useSelect(
		( select ) => {
			const {
				getIsSavingEvidenceForDispute,
				getIsUploadingEvidenceForDispute,
				getEvidenceTransientForDispute,
				getEvidenceUploadErrorsForDispute,
			} = select( STORE_NAME );

			return {
				isSavingEvidence: getIsSavingEvidenceForDispute( disputeId ),
				isUploadingEvidence: getIsUploadingEvidenceForDispute(
					disputeId
				),
				evidenceTransient: getEvidenceTransientForDispute( disputeId ),
				evidenceUploadErrors: getEvidenceUploadErrorsForDispute(
					disputeId
				),
			};
		},
		[ disputeId ]
	);

	const {
		saveEvidence,
		submitEvidence,
		updateEvidenceTransientForDispute,
		updateIsUploadingEvidenceForDispute,
		updateEvidenceUploadErrorsForDispute,
		uploadFileEvidenceForDispute,
	} = useDispatch( STORE_NAME );

	return {
		isSavingEvidence,
		isUploadingEvidence,
		evidenceTransient,
		evidenceUploadErrors,
		saveEvidence,
		submitEvidence,
		updateEvidenceTransientForDispute,
		updateIsUploadingEvidenceForDispute,
		updateEvidenceUploadErrorsForDispute,
		uploadFileEvidenceForDispute,
	};
};

export const useDisputes = ( { paged, per_page: perPage } ) =>
	useSelect(
		( select ) => {
			const { getDisputes, isResolving } = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
			};

			const disputes = getDisputes( query );
			const isLoading = isResolving( 'getDisputes', [ query ] );

			return { disputes, isLoading };
		},
		[ paged, perPage ]
	);
