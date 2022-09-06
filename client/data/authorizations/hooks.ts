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

export const useAuthorizations = ( {}: Query ): Authorizations =>
	useSelect( ( select ) => {
		const {
			getAuthorizations,
			getAuthorizationsError,
			isResolving,
		} = select( STORE_NAME );

		const query = {};

		return {
			authorizations: getAuthorizations( query ),
			authorizationsError: getAuthorizationsError( query ),
			isLoading: isResolving( 'getAuthorizations', [ query ] ),
		};
	}, [] );

export const useAuthorizationsSummary = ( {}: Query ): {
	authorizationsSummary: AuthorizationsSummary;
	isLoading: boolean;
} =>
	useSelect( ( select ) => {
		const { getAuthorizationsSummary, isResolving } = select( STORE_NAME );

		const query = {};

		return {
			authorizationsSummary: getAuthorizationsSummary( query ),
			isLoading: isResolving( 'getAuthorizationsSummary', [ query ] ),
		};
	} );

export const useAuthorization = (
	id: string,
	orderId: number,
	paymentIntentId: string,
	isCaptured = true // TODO: remove when getAuthorization switches to live data.
): {
	isLoading: boolean;
	doCaptureAuthorization: () => void;
	authorization: Authorization;
} => {
	const { authorization, isLoading } = useSelect( ( select ) => {
		const { getAuthorization, isResolving } = select( STORE_NAME );
		return {
			authorization: getAuthorization( id, isCaptured ),
			isLoading: isResolving( 'getAuthorization', [ id ] ),
		};
	} );

	const { submitCaptureAuthorization } = useDispatch( STORE_NAME );
	const doCaptureAuthorization = () =>
		submitCaptureAuthorization( id, orderId, paymentIntentId );

	return { authorization, isLoading, doCaptureAuthorization };
};
