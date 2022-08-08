/** @format */
/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import moment from 'moment';
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import { STORE_NAME } from '../constants';

// TODO: refine this type with more detailed information.
export interface Authorization {
	authorization_id: string;
	authorized_on: string;
	captured_by: string;
	order: OrderDetails;
	risk_level: 'normal' | 'high' | 'elevated';
	amount: number;
	customer_name: string;
	customer_email: string;
	customer_country: string;
}

interface Authorizations {
	authorizations: Authorization[];
	authorizationsError?: string;
	isLoading: boolean;
}

export interface AuthorizationsSummary {
	count?: number;
	total?: number;
	totalAmount?: number;
	currency?: string;
	store_currencies?: string[];
	customer_currencies?: string[];
}

export const useAuthorizations = ( {}: Query ): Authorizations =>
	useSelect( ( select ) => {
		const {
			getAuthorizations,
			getAuthorizationsError,
			isResolving,
		} = select( STORE_NAME );

		const query = {};

		const data = getAuthorizations( query );

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
