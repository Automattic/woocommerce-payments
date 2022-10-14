/** @format */

/**
 * External dependencies
 */
import { Query } from '@woocommerce/navigation';
import moment from 'moment';
import { dateI18n } from '@wordpress/date';
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	updateAuthorizations,
	updateAuthorization,
	updateAuthorizationsSummary,
} from './actions';
import { Authorization } from 'wcpay/types/authorizations';
import { ChargeCache } from 'wcpay/types/charges-cache';
import { NAMESPACE } from '../constants';

export function* getAuthorizations( query: Query ): Generator< unknown > {
	// TODO: fetch authorizations data from server endpoint.
	yield updateAuthorizations( query, [] );
}

export function* getAuthorization(
	paymentIntentId: string
): Generator< unknown > {
	try {
		let result;
		if ( paymentIntentId ) {
			result = yield apiFetch( {
				path: `${ NAMESPACE }/authorizations/${ paymentIntentId }`,
			} );
		}
		const chargeCache = result as ChargeCache;
		const isCaptured = chargeCache ? chargeCache.is_captured : false;
		const captureByDate = chargeCache
			? new Date( chargeCache.created )
			: new Date();
		yield updateAuthorization( {
			payment_intent_id: paymentIntentId,
			captured: isCaptured,
			capture_by: dateI18n(
				'M j, Y / g:iA',
				moment
					.utc( captureByDate )
					.add( '7', 'days' )
					.local()
					.toISOString()
			),
		} as Authorization );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving authorization.', 'woocommerce-payments' )
		);
	}
}

export function* getAuthorizationsSummary(
	query: Query
): Generator< unknown > {
	// TODO: fetch authorizations summary data from server endpoint.
	yield updateAuthorizationsSummary( query, {} );
}
