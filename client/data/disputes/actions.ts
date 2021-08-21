/* eslint-disable @typescript-eslint/explicit-module-boundary-types */
/** @format */

/**
 * External dependencies
 */
import { select, dispatch } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import { __, sprintf } from '@wordpress/i18n';
import { some } from 'lodash';

/**
 * Internal dependencies
 */
import { NAMESPACE, STORE_NAME } from '../constants';
import wcpayTracks from 'tracks';

export function updateDispute( data: Dispute ) {
	return {
		type: 'SET_DISPUTE' as const,
		query: undefined, // here to fit the reducer type.
		data,
	};
}

export function updateDisputes(
	query: Record< string, string >,
	data: Dispute[]
) {
	return {
		type: 'SET_DISPUTES' as const,
		query,
		data,
	};
}

export function updateIsSavingEvidenceForDispute(
	disputeId: string,
	isSavingEvidence: boolean
) {
	return {
		type: 'SET_IS_SAVING_EVIDENCE_FOR_DISPUTE' as const,
		data: {
			isSavingEvidence,
			id: disputeId,
		},
	};
}

export function updateEvidenceTransientForDispute(
	disputeId: string,
	evidenceTransient: Partial< Evidence >
) {
	return {
		type: 'SET_EVIDENCE_TRANSIENT_FOR_DISPUTE' as const,
		data: { id: disputeId, evidenceTransient },
	};
}

export function updateIsUploadingEvidenceForDispute(
	disputeId: string,
	key: string,
	isUploadingEvidenceForDispute: boolean
) {
	return {
		type: 'SET_IS_UPLOADING_EVIDENCE_FOR_DISPUTE' as const,
		data: { id: disputeId, key, isUploadingEvidenceForDispute },
	};
}

export function updateEvidenceUploadErrorsForDispute(
	disputeId: string,
	key: string,
	errorMessage: string
) {
	return {
		type: 'SET_EVIDENCE_UPLOAD_ERRORS_FOR_DISPUTE' as const,
		data: { id: disputeId, key, errorMessage },
	};
}

export function* acceptDispute( id: string ) {
	try {
		yield dispatch( STORE_NAME ).startResolution( 'getDispute', [ id ] );

		const dispute: Dispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ id }/close`,
			method: 'post',
		} );

		yield updateDispute( dispute );
		yield dispatch( STORE_NAME ).finishResolution( 'getDispute', [ id ] );

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
		yield dispatch( 'core/notices' ).createSuccessNotice( message );
	} catch ( e ) {
		const message = __(
			'There has been an error accepting the dispute. Please try again later.',
			'woocommerce-payments'
		);
		wcpayTracks.recordEvent( 'wcpay_dispute_accept_failed' );
		yield dispatch( 'core/notices' ).createErrorNotice( message );
	}
}

function* handleSaveSuccess( id: string, submit: boolean ) {
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
	yield dispatch( 'core/notices' ).createSuccessNotice( message, {
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

function* handleSaveError( err: { message: string }, submit: boolean ) {
	wcpayTracks.recordEvent(
		submit
			? 'wcpay_dispute_submit_evidence_failed'
			: 'wcpay_dispute_save_evidence_failed'
	);

	const message = submit
		? __( 'Failed to submit evidence. (%s)', 'woocommerce-payments' )
		: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
	yield dispatch( 'core/notices' ).createErrorNotice(
		sprintf( message, err.message )
	);
}

export function* submitEvidence( disputeId: string, evidence: Evidence ) {
	let error = null;
	const dispute: Dispute = yield select( STORE_NAME ).getDispute( disputeId );
	const isUploading: Record< string, boolean > = yield select(
		STORE_NAME
	).getIsUploadingEvidenceForDispute( disputeId );

	// Prevent submit if upload is in progress.
	if ( some( isUploading ) ) {
		dispatch( 'core/notices' ).createInfoNotice(
			__(
				'Please wait until file upload is finished',
				'woocommerce-payments'
			)
		);
		return;
	}

	yield updateIsSavingEvidenceForDispute( disputeId, true );

	try {
		wcpayTracks.recordEvent( 'wcpay_dispute_submit_evidence_clicked' );

		const updatedDispute: Dispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ disputeId }`,
			method: 'post',
			data: {
				// Send full evidence, as submission does not appear to work without new evidence despite being optional.
				evidence: { ...dispute.evidence, ...evidence },
				metadata: dispute.metadata,
				submit: true,
			},
		} );

		yield updateEvidenceTransientForDispute( disputeId, {} );
		yield handleSaveSuccess( disputeId, true );
		yield updateDispute( updatedDispute );
	} catch ( err ) {
		yield handleSaveError( err, true );
		error = err;
	} finally {
		yield updateIsSavingEvidenceForDispute( disputeId, false );
	}

	return null === error;
}

export function* saveEvidence( disputeId: string, evidence: Evidence ) {
	let error = null;
	const dispute: Dispute = yield select( STORE_NAME ).getDispute( disputeId );
	const isUploading: Record< string, boolean > = yield select(
		STORE_NAME
	).getIsUploadingEvidenceForDispute( disputeId );

	// Prevent submit if upload is in progress.
	if ( some( isUploading ) ) {
		dispatch( 'core/notices' ).createInfoNotice(
			__(
				'Please wait until file upload is finished',
				'woocommerce-payments'
			)
		);
		return;
	}

	yield updateIsSavingEvidenceForDispute( disputeId, true );

	try {
		wcpayTracks.recordEvent( 'wcpay_dispute_save_evidence_clicked' );

		const updatedDispute: Dispute = yield apiFetch( {
			path: `${ NAMESPACE }/disputes/${ disputeId }`,
			method: 'post',
			data: {
				// Send full evidence, as submission does not appear to work without new evidence despite being optional.
				evidence: { ...dispute.evidence, ...evidence },
				metadata: dispute.metadata,
				submit: false,
			},
		} );

		yield updateEvidenceTransientForDispute( disputeId, {} );
		yield handleSaveSuccess( disputeId, false );
		yield updateDispute( updatedDispute );
	} catch ( err ) {
		yield handleSaveError( err, false );
		error = err;
	} finally {
		yield updateIsSavingEvidenceForDispute( disputeId, false );
	}

	return null === error;
}

const fileSizeExceeded = ( dispute: Dispute, latestFileSize: number ) => {
	const fileSizeLimitInBytes = 4500000;
	const fileSizes = dispute.fileSize ? Object.values( dispute.fileSize ) : [];
	const totalFileSize =
		fileSizes.reduce( ( acc, fileSize ) => acc + fileSize, 0 ) +
		latestFileSize;
	if ( fileSizeLimitInBytes < totalFileSize ) {
		dispatch( 'core/notices' ).createInfoNotice(
			__(
				"The files you've attached to this dispute as evidence will exceed the limit for a " +
					"dispute's total size. Try using smaller files as evidence. Hint: if you've attached " +
					'images, you might want to try providing them in lower resolutions.',
				'woocommerce-payments'
			)
		);
		return true;
	}
};

export function* uploadFileEvidenceForDispute(
	disputeId: string,
	key: string,
	file: Blob
) {
	if ( ! file ) {
		return;
	}

	const dispute: Dispute = yield select( STORE_NAME ).getDispute( disputeId );
	const evidenceTransient: Evidence = yield select(
		STORE_NAME
	).getEvidenceTransientForDispute( disputeId );

	if ( fileSizeExceeded( dispute, file.size ) ) {
		return;
	}

	wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_started', {
		type: key,
	} );

	const body = new FormData();
	body.append( 'file', file );
	body.append( 'purpose', 'dispute_evidence' );

	// Set request status for UI.
	yield updateDispute( {
		...dispute,
		metadata: { ...dispute.metadata, [ key ]: '' },
	} );
	yield updateIsUploadingEvidenceForDispute( disputeId, key, true );
	yield updateEvidenceUploadErrorsForDispute( disputeId, key, '' );

	// Force reload evidence components.
	yield updateEvidenceTransientForDispute( disputeId, {
		...evidenceTransient,
		[ key ]: '',
	} );

	try {
		const uploadedFile: {
			filename: string;
			size: number;
			id: string;
		} = yield apiFetch( {
			path: '/wc/v3/payments/file',
			method: 'post',
			body,
		} );
		// Store uploaded file name in metadata to display in submitted evidence or saved for later form.
		yield updateDispute( {
			...dispute,
			metadata: {
				...dispute.metadata,
				[ key ]: uploadedFile.filename,
			},
			fileSize: { ...dispute.fileSize, [ key ]: uploadedFile.size },
		} );
		yield updateIsUploadingEvidenceForDispute( disputeId, key, false );
		yield updateEvidenceTransientForDispute( disputeId, {
			...evidenceTransient,
			[ key ]: uploadedFile.id,
		} );

		wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_success', {
			type: key,
		} );
	} catch ( err ) {
		wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_failed', {
			message: err.message,
		} );

		yield updateDispute( {
			...dispute,
			metadata: { ...dispute.metadata, [ key ]: '' },
		} );
		yield updateIsUploadingEvidenceForDispute( disputeId, key, false );
		yield updateEvidenceUploadErrorsForDispute(
			disputeId,
			key,
			err.message
		);

		// Force reload evidence components.
		yield updateEvidenceTransientForDispute( disputeId, {
			...evidenceTransient,
			[ key ]: '',
		} );
	}
}

export type DisputesAction = ReturnType<
	typeof updateDisputes | typeof updateDispute
>;
export type EvidenceAction = ReturnType<
	typeof updateEvidenceTransientForDispute
>;
export type EvidenceUploadErrorAction = ReturnType<
	typeof updateEvidenceUploadErrorsForDispute
>;
export type SavingEvidenceStatusAction = ReturnType<
	typeof updateIsSavingEvidenceForDispute
>;
export type EvidenceUploadStatusAction = ReturnType<
	typeof updateIsUploadingEvidenceForDispute
>;
