/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { __, sprintf } from '@wordpress/i18n';
import Currency from '@woocommerce/currency';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { STORE_NAME } from '../constants';

// TODO: Use the proper WCPay currency.
const currency = new Currency();

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

export function* submitInstantDeposit( transactionIds ) {
	try {
		yield dispatch( STORE_NAME, 'startResolution', 'getInstantDeposit', [
			transactionIds,
		] );

		const deposit = yield apiFetch( {
			path: '/wc/v3/payments/deposits',
			method: 'POST',
			data: {
				type: 'instant',
				// eslint-disable-next-line camelcase
				transaction_ids: transactionIds,
			},
		} );

		yield updateInstantDeposit( deposit );
		yield dispatch( STORE_NAME, 'finishResolution', 'getInstantDeposit', [
			transactionIds,
		] );

		// Need to invalidate the resolution so that the components will render again.
		yield dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getDeposits'
		);
		yield dispatch(
			STORE_NAME,
			'invalidateResolutionForStoreSelector',
			'getDepositsOverview'
		);

		yield dispatch(
			'core/notices',
			'createSuccessNotice',
			sprintf(
				__(
					'Instant deposit for %s in transit.',
					'woocommerce-payments'
				),
				currency.formatCurrency( deposit.amount / 100 )
			),
			{
				actions: [
					{
						label: __( 'View details', 'woocommerce-payments' ),
						url: addQueryArgs( 'admin.php', {
							page: 'wc-admin',
							path: '/payments/deposits/details',
							id: deposit.id,
						} ),
					},
				],
			}
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
