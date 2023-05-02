/** @format */
/**
 * External dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';
import {
	AuthorizationsSummary,
	Authorizations,
	Authorization,
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
				order: order || 'asc',
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

export const useAuthorization = (
	paymentIntentId: string,
	orderId: number,
	requiresCapture = true
): {
	isLoading: boolean;
	isRequesting: boolean;
	doCaptureAuthorization: () => void;
	doCancelAuthorization: () => void;
	authorization?: Authorization;
} => {
	const { authorization, isRequesting, isLoading } = useSelect(
		( select ) => {
			const { getAuthorization, getIsRequesting, isResolving } = select(
				STORE_NAME
			);
			return {
				authorization: requiresCapture
					? getAuthorization( paymentIntentId )
					: null,
				isLoading: isResolving( 'getAuthorization', [
					paymentIntentId,
				] ),
				isRequesting: getIsRequesting(),
			};
		}
	);

	const {
		submitCaptureAuthorization,
		submitCancelAuthorization,
	} = useDispatch( STORE_NAME );

	const doCaptureAuthorization = () =>
		submitCaptureAuthorization( paymentIntentId, orderId );

	const doCancelAuthorization = () =>
		submitCancelAuthorization( paymentIntentId, orderId );

	return {
		authorization,
		isLoading,
		isRequesting,
		doCaptureAuthorization,
		doCancelAuthorization,
	};
};
