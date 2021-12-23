/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import type { Dispute, Disputes, DisputesSummary } from 'wcpay/types/disputes';
import { STORE_NAME } from '../constants';

export const useDispute = (
	id: string
): {
	dispute: Dispute;
	isLoading: boolean;
	doAccept: () => void;
} => {
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

export const useDisputeEvidence = (): {
	updateDispute: ( data: Dispute ) => void;
} => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
};

export const useDisputes = ( { paged, per_page: perPage }: Query ): Disputes =>
	useSelect(
		( select ) => {
			const { getDisputes, isResolving } = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged ?? '', 10 ) )
					? '1'
					: paged,
				perPage: Number.isNaN( parseInt( perPage ?? '', 10 ) )
					? '25'
					: perPage,
			};

			return {
				disputes: getDisputes( query ),
				isLoading: isResolving( 'getDisputes', [ query ] ),
			};
		},
		[ paged, perPage ]
	);

export const useDisputesSummary = (): DisputesSummary =>
	useSelect( ( select ) => {
		const { getDisputesSummary, isResolving } = select( STORE_NAME );

		return {
			disputesSummary: getDisputesSummary(),
			isLoading: isResolving( 'getDisputesSummary' ),
		};
	}, [] );
