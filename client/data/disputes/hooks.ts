/** @format */

/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '../constants';
// Technically an internal dependency type declaration.
import { WCAdminTableQuery } from '@woocommerce/navigation';

export const useDispute = ( id: string ) => {
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

	const { acceptDispute } = useDispatch( STORE_NAME );
	const doAccept = () => acceptDispute( id );

	return { dispute, isLoading, doAccept };
};

export const useDisputeEvidence = () => {
	const { updateDispute } = useDispatch( STORE_NAME );
	return { updateDispute };
};

// eslint-disable-next-line camelcase
export const useDisputes = ( { paged, per_page: perPage }: WCAdminTableQuery ) =>
	useSelect(
		( select ) => {
			const { getDisputes, isResolving } = select( STORE_NAME );

			const query = {
				paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
				perPage: Number.isNaN( parseInt( perPage, 10 ) )
					? '25'
					: perPage,
			};

			const disputes: any[] = getDisputes( query );
			const isLoading: boolean = isResolving( 'getDisputes', [ query ] );

			return { disputes, isLoading };
		},
		[ paged, perPage ]
	);
