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

/**
 * Retrieve a single deposit from the deposits API.
 *
 * @param {string} id Identifier for specified deposit to retrieve.
 */
export function* getDeposit( id ) {
	const path = addQueryArgs( `${ NAMESPACE }/deposits/${ id }` );

	try {
		const result = yield apiFetch( { path } );
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

/*eslint-disable camelcase*/
const formatQueryFilters = ( query ) => ( {
	currency_is: query.currencyIs,
} );
/*eslint-enable camelcase*/

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
		...formatQueryFilters( query ),
	} );

	try {
		const results = yield apiFetch( { path } ) || {};

		yield updateDeposits( query, results.data );
		yield updateDepositsCount( results.total_count );

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
