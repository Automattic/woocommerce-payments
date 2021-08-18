/** @format */

/**
 * External dependencies
 */
import { resolveSelect } from '@wordpress/data';
import { apiFetch, dispatch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import TYPES from './action-types';
import wcpayTracks from 'tracks';

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

export function updateIsSavingEvidenceForDispute(
	disputeId,
	isSavingEvidence
) {
	return {
		type: TYPES.SET_IS_SAVING_EVIDENCE_FOR_DISPUTE,
		data: {
			isSavingEvidence,
			id: disputeId,
		},
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
			addQueryArgs( 'admin.php', {
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

function* handleSaveSuccess( id, submit ) {
	const message = submit
		? __( 'Evidence submitted!', 'woocommerce-payments' )
		: __( 'Evidence saved!', 'woocommerce-payments' );
	const href = addQueryArgs( 'admin.php', {
		page: 'wc-admin',
		path: '/payments/disputes',
	} );

	wcpayTracks.recordEvent(
		submit
			? 'wcpay_dispute_submit_evidence_success'
			: 'wcpay_dispute_save_evidence_success'
	);
	/*
			We rely on WC-Admin Transient notices to display success message.
			https://github.com/woocommerce/woocommerce-admin/tree/master/client/layout/transient-notices.
		*/
	yield dispatch( 'core/notices', 'createSuccessNotice', message, {
		actions: [
			{
				label: submit
					? __( 'View submitted evidence', 'woocommerce-payments' )
					: __(
							'Return to evidence submission',
							'woocommerce-payments'
					  ),
				url: addQueryArgs( 'admin.php', {
					page: 'wc-admin',
					path: '/payments/disputes/challenge',
					id: id,
				} ),
			},
		],
	} );

	getHistory().push( href );
}

function* handleSaveError( err, submit ) {
	wcpayTracks.recordEvent(
		submit
			? 'wcpay_dispute_submit_evidence_failed'
			: 'wcpay_dispute_save_evidence_failed'
	);

	const message = submit
		? __( 'Failed to submit evidence. (%s)', 'woocommerce-payments' )
		: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
	yield dispatch(
		'core/notices',
		'createErrorNotice',
		sprintf( message, err.message )
	);
}

export function* saveDispute( id, submit, evidence, setEvidence ) {
	const dispute = yield resolveSelect( STORE_NAME, 'getDispute', id );

	yield updateIsSavingEvidenceForDispute( id, true );

	try {
		wcpayTracks.recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_clicked'
				: 'wcpay_dispute_save_evidence_clicked'
		);

		const { metadata } = dispute;
		const updatedDispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }`,
			method: 'post',
			data: {
				// Send full evidence, as submission does not appear to work without new evidence despite being optional.
				evidence: { ...dispute.evidence, ...evidence },
				metadata,
				submit,
			},
		} );
		handleSaveSuccess( id, submit );
		setEvidence( {} );
		yield updateDispute( updatedDispute );
	} catch ( err ) {
		handleSaveError( err, submit );
	} finally {
		yield updateIsSavingEvidenceForDispute( id, false );
	}
}
