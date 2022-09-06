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

// eslint-disable-next-line @typescript-eslint/no-unused-vars
export function* getAuthorization(
	id: string,
	isCaptured: boolean // TODO: remove when getAuthorization switches to live data.
): Generator< unknown > {
	yield updateAuthorization( {
		authorization_id: id,
		captured: isCaptured,
	} as Authorization );
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	yield updateAuthorizationsSummary( query, {} );
}
