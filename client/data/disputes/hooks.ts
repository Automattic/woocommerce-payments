/* eslint-disable @typescript-eslint/explicit-module-boundary-types */
/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useDispute = ( id: string ) => {
	const { dispute, isLoading } = useSelect(
		( select ) => {
			const { getDispute, isResolving } = select( STORE_NAME );

			return {
				dispute: getDispute< Dispute >( id ),
				isLoading: isResolving< boolean >( 'getDispute', [ id ] ),
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

export const useDisputeEvidence = ( disputeId: string ) => {
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
				isSavingEvidence: getIsSavingEvidenceForDispute< boolean >(
					disputeId
				),
				isUploadingEvidence: getIsUploadingEvidenceForDispute< {
					[ key: string ]: boolean;
				} >( disputeId ),
				evidenceTransient: getEvidenceTransientForDispute< Evidence >(
					disputeId
				),
				evidenceUploadErrors: getEvidenceUploadErrorsForDispute< {
					[ key: string ]: string;
				} >( disputeId ),
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

export const useDisputes = ( {
	paged,
	per_page: perPage,
}: {
	paged: string;
	per_page: string;
} ) =>
	useSelect(
		( select ) => {
			const { getDisputes, isResolving } = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
			};

			const disputes = getDisputes< Dispute[] >( query );
			const isLoading = isResolving< boolean >( 'getDisputes', [
				query,
			] );

			return { disputes, isLoading };
		},
		[ paged, perPage ]
	);
