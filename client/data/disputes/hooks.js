/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';

export const useDispute = ( id ) => {
	const {
		dispute,
		isLoading,
		isSavingEvidence,
		evidenceTransient,
	} = useSelect(
		( select ) => {
			const {
				getDispute,
				isResolving,
				getIsSavingEvidenceForDispute,
				getEvidenceTransientForDispute,
			} = select( STORE_NAME );

			return {
				dispute: getDispute( id ),
				isLoading: isResolving( 'getDispute', [ id ] ),
				isSavingEvidence: getIsSavingEvidenceForDispute( id ),
				evidenceTransient: getEvidenceTransientForDispute( id ),
			};
		},
		[ id ]
	);

	const {
		acceptDispute,
		saveEvidence,
		submitEvidence,
		updateEvidenceTransientForDispute,
	} = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( id );

	return {
		dispute,
		isLoading,
		isSavingEvidence,
		evidenceTransient,
		doAccept,
		saveEvidence,
		submitEvidence,
		updateEvidenceTransientForDispute,
	};
};

export const useDisputeEvidence = () => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
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
