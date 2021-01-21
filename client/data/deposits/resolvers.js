/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import {
	updateDeposit,
	updateDeposits,
	updateDepositsCount,
	updateErrorForDepositQuery,
	updateDepositsOverview,
	updateErrorForDepositsOverview,
} from './actions';

const convertStripePayoutToDeposit = ( stripePayout ) => ( {
	id: stripePayout.id,
	date: +new Date( stripePayout.arrival_date * 1000 ),
	type: 0 < stripePayout.amount ? 'deposit' : 'withdrawal',
	amount: stripePayout.amount,
	currency: stripePayout.currency,
	status: stripePayout.status,
	bankAccount:
		stripePayout.destination.bank_name &&
		`${ stripePayout.destination.bank_name } ` +
			`•••• ${ stripePayout.destination.last4 } ` +
			`(${ stripePayout.destination.currency.toUpperCase() })`,
} );

/**
 * Retrieve a single deposit from the deposits API.
 *
 * @param {string} id Identifier for specified deposit to retrieve.
 */
export function* getDeposit( id ) {
	const path = addQueryArgs( `${ NAMESPACE }/deposits/${ id }` );

	try {
		let result = yield apiFetch( { path } );

		// If using Stripe API objects directly, map to deposits.
		// TODO Remove this mapping when these deposits are formatted by the server.
		if ( 'payout' === result.object ) {
			result = convertStripePayoutToDeposit( result );
		}

		yield updateDeposit( result );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving deposit.', 'woocommerce-payments' )
		);
	}
}

/**
 * Retrieve deposits overview from the deposits API.
 */
export function* getDepositsOverview() {
	const path = addQueryArgs( `${ NAMESPACE }/deposits/overview` );

	try {
		const result = yield apiFetch( { path } );

		// If using Stripe API objects directly, map to deposits.
		// TODO Remove this mapping when these deposits are formatted by the server.
		if ( result.last_deposit && 'payout' === result.last_deposit.object ) {
			// eslint-disable-next-line camelcase
			result.last_deposit = convertStripePayoutToDeposit(
				result.last_deposit
			);
		}
		if ( result.next_deposit && 'payout' === result.next_deposit.object ) {
			// eslint-disable-next-line camelcase
			result.next_deposit = convertStripePayoutToDeposit(
				result.next_deposit
			);
		}

		yield updateDepositsOverview( result );
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving deposits overview.', 'woocommerce-payments' )
		);
		yield updateErrorForDepositsOverview( e );
	}
}

/**
 * Retrieves a series of deposits from the deposits list API.
 *
 * @param {string} query Data on which to parameterize the selection.
 */
export function* getDeposits( query ) {
	const path = addQueryArgs( `${ NAMESPACE }/deposits`, {
		page: query.paged,
		pagesize: query.perPage,
		sort: query.orderby,
		direction: query.order,
	} );

	try {
		const results = yield apiFetch( { path } ) || {};

		// If using Stripe API objects directly, map to deposits.
		// TODO Remove this mapping when these deposits are formatted by the server.
		for ( const i in results.data ) {
			if ( results.data[ i ] && results.data[ i ].object === 'payout' ) {
				results.data[ i ] = convertStripePayoutToDeposit(
					results.data[ i ]
				);
			}
		}

		yield updateDeposits( query, results.data );
		yield updateDepositsCount( results.count );

		// Update resolution state on getDeposit selector for each result.
		for ( const i in results.data ) {
			yield dispatch( STORE_NAME, 'finishResolution', 'getDeposit', [
				results.data[ i ].id,
			] );
		}
	} catch ( e ) {
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error retrieving deposits.', 'woocommerce-payments' )
		);
		yield updateErrorForDepositQuery( query, null, e );
	}
}
