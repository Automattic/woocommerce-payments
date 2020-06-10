/** @format */

/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import { STORE_NAME } from '../constants';

// eslint-disable-next-line camelcase
export const useDisputes = ( { paged, per_page: perPage } ) => useSelect( select => {
	const { getDisputes, isResolving } = select( STORE_NAME );

	const query = {
		paged: Number.isNaN( parseInt( paged, 10 ) ) ? '1' : paged,
		perPage: Number.isNaN( parseInt( perPage, 10 ) ) ? '25' : perPage,
	};

	const disputes = getDisputes( query );
	const isLoading = isResolving( 'getDisputes', [ query ] );

	return { disputes, isLoading };
}, [ paged, perPage ] );
