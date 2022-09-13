/** @format */

/**
 * External dependencies
 */
import { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
} from './actions';
import { Authorization } from 'wcpay/types/authorizations';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	yield updateAuthorizations( query, [] );
}

export function* getAuthorization(
	paymentIntentId: string
): Generator< unknown > {
	yield updateAuthorization( {
		payment_intent_id: paymentIntentId,
	} as Authorization );
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	yield updateAuthorizationsSummary( query, {} );
}
