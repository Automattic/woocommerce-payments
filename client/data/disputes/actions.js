/** @format */

/**
 * External dependencies
 */
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { getHistory } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import TYPES from './action-types';
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';

export function updateDispute( data ) {
	return {
		type: TYPES.SET_DISPUTE,
		data,
	};
}

export function updateDisputes( query, data ) {
	return {
		type: TYPES.SET_DISPUTES,
		query,
		data,
	};
}

export function* acceptDispute( id ) {
	try {
		yield dispatch( STORE_NAME, 'startResolution', 'getDispute', [ id ] );

		const dispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }/close`,
			method: 'post',
		} );

		yield updateDispute( dispute );
		yield dispatch( STORE_NAME, 'finishResolution', 'getDispute', [ id ] );

		// Redirect to Disputes list.
		getHistory().push(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
			} )
		);

		wcpayTracks.recordEvent( 'wcpay_dispute_accept_success' );
		const message = dispute.order
			? sprintf(
					/* translators: #%s is an order number, e.g. 15 */
					__(
						'You have accepted the dispute for order #%s.',
						'woocommerce-payments'
					),
					dispute.order.number
			  )
			: __( 'You have accepted the dispute.', 'woocommerce-payments' );
		yield dispatch( 'core/notices', 'createSuccessNotice', message );
	} catch ( e ) {
		const message = __(
			'There has been an error accepting the dispute. Please try again later.',
			'woocommerce-payments'
		);
		wcpayTracks.recordEvent( 'wcpay_dispute_accept_failed' );
		yield dispatch( 'core/notices', 'createErrorNotice', message );
	}
}
