/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __ } from '@wordpress/i18n';

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { STORE_NAME } from '../constants';

export function updateDeposit( data ) {
	return {
		type: TYPES.SET_DEPOSIT,
		data,
	};
}

export function updateDepositsOverview( data ) {
	return {
		type: TYPES.SET_DEPOSITS_OVERVIEW,
		data,
	};
}

export function updateErrorForDepositsOverview( data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DEPOSITS_OVERVIEW,
		error,
	};
}

export function updateDeposits( query, data ) {
	return {
		type: TYPES.SET_DEPOSITS,
		query,
		data,
	};
}

export function updateErrorForDepositQuery( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DEPOSIT_QUERY,
		query,
		data,
		error,
	};
}

export function updateInstantDeposit( data ) {
	return {
		type: TYPES.SET_INSTANT_DEPOSIT,
		data,
	};
}

export function* submitInstantDeposit( transaction_ids ) {
	try {
		yield dispatch( STORE_NAME, 'startResolution', 'getInstantDeposit', [ transaction_ids ] );

		const deposit = yield apiFetch( {
			path: '/wc/v3/payments/deposits',
			method: 'POST',
			data: {
				type: 'instant',
				// eslint-disable-next-line camelcase
				transaction_ids,
			},
		} );

		yield updateInstantDeposit( deposit );
		yield dispatch( STORE_NAME, 'finishResolution', 'getInstantDeposit', [ transaction_ids ] );

		yield dispatch(
			'core/notices',
			'createSuccessNotice',
			__( 'Instant deposit successful.', 'woocommerce-payments' )
		);
	} catch ( e ) {
		yield updateInstantDeposit( e );
		yield dispatch(
			'core/notices',
			'createErrorNotice',
			__( 'Error creating instant deposit.', 'woocommerce-payments' )
		);
	}
}
