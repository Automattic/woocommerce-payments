/** @format */

/**
 * External dependencies
 */
import { apiFetch } from '@wordpress/data-controls';
import { controls } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import TYPES from './action-types';
import wcpayTracks from 'tracks';
import { getAdminUrl } from 'wcpay/utils';
import { getPaymentIntent } from '../payment-intents/resolvers';

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

export function updateDisputesSummary( query, data ) {
	return {
		type: TYPES.SET_DISPUTES_SUMMARY,
		query,
		data,
	};
}

export function* acceptDispute( id ) {
	try {
		yield controls.dispatch( STORE_NAME, 'startResolution', 'getDispute', [
			id,
		] );

		const dispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }/close`,
			method: 'post',
		} );

		yield updateDispute( dispute );
		yield controls.dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
			id,
		] );

		// Redirect to Disputes list.
		window.location.replace(
			getAdminUrl( {
				page: 'wc-admin',
				path: '/payments/disputes',
				filter: 'awaiting_response',
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
		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			message
		);
	} catch ( e ) {
		const message = __(
			'There has been an error accepting the dispute. Please try again later.',
			'woocommerce-payments'
		);
		wcpayTracks.recordEvent( 'wcpay_dispute_accept_failed' );
		yield controls.dispatch( 'core/notices', 'createErrorNotice', message );
	}
}

// This function handles the dispute acceptance flow from the Transaction Details screen.
// It will become the default acceptDispute function once the feature flag
// '_wcpay_feature_dispute_on_transaction_page' is enabled by default.
export function* acceptTransactionDetailsDispute( dispute ) {
	const { id, payment_intent: paymentIntent } = dispute;

	try {
		yield controls.dispatch( STORE_NAME, 'startResolution', 'getDispute', [
			id,
		] );

		const updatedDispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }/close`,
			method: 'post',
		} );

		yield updateDispute( updatedDispute );

		// Fetch and update the payment intent associated with the dispute
		// to reflect changes to the dispute on the Transaction Details screen.
		yield getPaymentIntent( paymentIntent );

		yield controls.dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
			id,
		] );

		wcpayTracks.recordEvent( 'wcpay_dispute_accept_success' );
		const message = updatedDispute.order
			? sprintf(
					/* translators: #%s is an order number, e.g. 15 */
					__(
						'You have accepted the dispute for order #%s.',
						'woocommerce-payments'
					),
					updatedDispute.order.number
			  )
			: __( 'You have accepted the dispute.', 'woocommerce-payments' );
		yield controls.dispatch(
			'core/notices',
			'createSuccessNotice',
			message
		);
	} catch ( e ) {
		const message = __(
			'There has been an error accepting the dispute. Please try again later.',
			'woocommerce-payments'
		);
		wcpayTracks.recordEvent( 'wcpay_dispute_accept_failed' );
		yield controls.dispatch( 'core/notices', 'createErrorNotice', message );
		yield controls.dispatch( STORE_NAME, 'finishResolution', 'getDispute', [
			id,
		] );
	}
}
