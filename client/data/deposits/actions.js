/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { dispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import { formatCurrency } from 'utils/currency';

/**
 * Internal Dependencies
 */
import TYPES from './action-types';
import { STORE_NAME } from '../constants';
import { getAdminUrl } from 'wcpay/utils';

export function updateDeposit( data ) {
	return {
		type: TYPES.SET_DEPOSIT,
		data,
	};
}

export function updateAllDepositsOverviews( data ) {
	return {
		type: TYPES.SET_ALL_DEPOSITS_OVERVIEWS,
		data,
	};
}

export function updateErrorForAllDepositsOverviews( data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_ALL_DEPOSITS_OVERVIEWS,
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

export function updateDepositsCount( data ) {
	return {
		type: TYPES.SET_DEPOSITS_COUNT,
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

export function updateDepositsSummary( query, data ) {
	return {
		type: TYPES.SET_DEPOSITS_SUMMARY,
		query,
		data,
	};
}

export function updateErrorForDepositsSummary( query, data, error ) {
	return {
		type: TYPES.SET_ERROR_FOR_DEPOSITS_SUMMARY,
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

export function* submitInstantDeposit( currency ) {
	try {
		yield dispatch( STORE_NAME ).startResolution( 'getInstantDeposit', [
			currency,
		] );

		const deposit = yield apiFetch( {
			path: '/wc/v3/payments/deposits',
			method: 'POST',
			data: {
				type: 'instant',
				currency,
			},
		} );

		yield updateInstantDeposit( deposit );

		// Invalidate deposits and deposits overview queries to ensure that the UI is updated with fresh data.
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getDeposits'
		);
		yield dispatch( STORE_NAME ).invalidateResolutionForStoreSelector(
			'getAllDepositsOverviews'
		);

		yield dispatch( 'core/notices' ).createSuccessNotice(
			sprintf(
				__(
					'Instant deposit for %s in transit.',
					'woocommerce-payments'
				),
				formatCurrency( deposit.amount )
			),
			{
				actions: [
					{
						label: __( 'View details', 'woocommerce-payments' ),
						url: getAdminUrl( {
							page: 'wc-admin',
							path: '/payments/deposits/details',
							id: deposit.id,
						} ),
					},
				],
			}
		);
	} catch {
		yield dispatch( 'core/notices' ).createErrorNotice(
			__( 'Error creating instant deposit.', 'woocommerce-payments' )
		);
	} finally {
		yield dispatch( STORE_NAME ).finishResolution( 'getInstantDeposit', [
			currency,
		] );
	}
}
