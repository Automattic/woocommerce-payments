/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';
import { Dispute } from 'wcpay/types/disputes';

interface DisputeObj {
	isLoading: boolean;
	dispute: Dispute;
	doAccept: () => void;
}

interface DisputesResponse {
	disputes: unknown;
	isLoading: unknown;
}

export const useDispute = ( id: string ): DisputeObj => {
	const { dispute, isLoading } = useSelect(
		( select ) => {
			const { getDispute, isResolving } = select( STORE_NAME );

			return {
				dispute: <Dispute>getDispute( id ),
				isLoading: <boolean>isResolving( 'getDispute', [ id ] ),
			};
		},
		[ id ]
	);

	const { acceptDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( id );

	return { dispute, isLoading, doAccept };
};

export const useDisputeEvidence = () => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
};

export const useDisputes = ( {
	paged,
	per_page: perPage,
}: {
	paged: string;
	per_page: string;
} ): DisputesResponse =>
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
