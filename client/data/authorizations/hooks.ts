/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import {
	AuthorizationsSummary,
	Authorizations,
} from 'wcpay/types/authorizations';

export const useAuthorizations = ( {
	paged,
	// eslint-disable-next-line @typescript-eslint/naming-convention
	per_page,
	orderby,
	order,
}: Query ): Authorizations =>
	useSelect(
		( select ) => {
			const {
				getAuthorizations,
				getAuthorizationsError,
				isResolving,
			} = select( STORE_NAME );

			const pagedQuery = Number.isNaN( parseInt( paged ?? '', 10 ) );
			const perPageQuery = Number.isNaN( parseInt( per_page ?? '', 10 ) );

			const query = {
				paged: pagedQuery ? '1' : paged,
				per_page: perPageQuery ? '25' : per_page,
				orderby: orderby || 'created',
				order: order || 'desc',
			};

			return {
				authorizations: getAuthorizations( query ),
				authorizationsError: getAuthorizationsError( query ),
				isLoading: isResolving( 'getAuthorizations', [ query ] ),
			};
		},
		[ paged, per_page, orderby, order ]
	);

export const useAuthorizationsSummary = (
	query: Query
): {
	authorizationsSummary: AuthorizationsSummary;
	isLoading: boolean;
} =>
	useSelect( ( select ) => {
		const {
			getAuthorizationsSummary,
			getAuthorizationsSummaryError,
			isResolving,
		} = select( STORE_NAME );

		return {
			authorizationsSummary: getAuthorizationsSummary( query ),
			authorizationsSummaryError: getAuthorizationsSummaryError( query ),
			isLoading: isResolving( 'getAuthorizationsSummary', [ query ] ),
		};
	} );
