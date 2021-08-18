/** @format **/

/**
 * External dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { addQueryArgs } from '@wordpress/url';
import { getHistory } from '@woocommerce/navigation';
import apiFetch from '@wordpress/api-fetch';

import { some, isMatchWith } from 'lodash';

/**
 * Internal dependencies.
 */
import '../style.scss';
import { useDisputeEvidence, useDispute } from 'wcpay/data';
import evidenceFields from './fields';
import useConfirmNavigation from 'utils/use-confirm-navigation';
import wcpayTracks from 'tracks';
import { DisputeEvidencePage } from './dispute-evidence-page';

const PRODUCT_TYPE_META_KEY = '__product_type';

/**
 * Retrieves product type from the dispute.
 *
 * @param {Object?} dispute Dispute object
 * @return {string} dispute product type
 */
const getDisputeProductType = ( dispute ) => {
	if ( ! dispute ) {
		return '';
	}

	let productType = dispute.metadata[ PRODUCT_TYPE_META_KEY ] || '';

	// Fallback to `multiple` when evidence submitted but no product type meta.
	if (
		! productType &&
		dispute.evidence_details &&
		dispute.evidence_details.has_evidence
	) {
		productType = 'multiple';
	}

	return productType;
};

// Temporary MVP data wrapper
export default ( { query } ) => {
	const path = `/wc/v3/payments/disputes/${ query.id }`;

	const { id: disputeId } = query;
	const { dispute, isLoading } = useDispute( disputeId );

	const { updateDispute } = useDisputeEvidence();

	const [ evidence, setEvidence ] = useState( {} ); // Evidence to update.
	const {
		createSuccessNotice,
		createErrorNotice,
		createInfoNotice,
	} = useDispatch( 'core/notices' );

	const pristine =
		! dispute ||
		isMatchWith(
			dispute.evidence,
			evidence,
			( disputeValue, formValue ) => {
				// Treat null and '' as equal values.
				if ( null === disputeValue && ! formValue ) {
					return true;
				}
			}
		);

	const confirmationNavigationCallback = useConfirmNavigation( () => {
		if ( pristine ) {
			return;
		}

		return __(
			'There are unsaved changes on this page. Are you sure you want to leave and discard the unsaved changes?',
			'woocommerce-payments'
		);
	} );

	useEffect( confirmationNavigationCallback, [
		pristine,
		confirmationNavigationCallback,
	] );

	const updateEvidence = ( key, value ) =>
		setEvidence( ( e ) => ( { ...e, [ key ]: value } ) );
	const isUploadingEvidence = () => some( dispute.isUploading );

	const doRemoveFile = ( key ) => {
		updateEvidence( key, '' );
		updateDispute( {
			...dispute,
			metadata: { ...dispute.metadata, [ key ]: '' },
			uploadingErrors: { ...dispute.uploadingErrors, [ key ]: '' },
			fileSize: { ...dispute.fileSize, [ key ]: 0 },
		} );
	};

	const fileSizeExceeded = ( latestFileSize ) => {
		const fileSizeLimitInBytes = 4500000;
		const fileSizes = dispute.fileSize
			? Object.values( dispute.fileSize )
			: [];
		const totalFileSize =
			fileSizes.reduce( ( acc, fileSize ) => acc + fileSize, 0 ) +
			latestFileSize;
		if ( fileSizeLimitInBytes < totalFileSize ) {
			createInfoNotice(
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

	const doUploadFile = async ( key, file ) => {
		if ( ! file ) {
			return;
		}

		if ( fileSizeExceeded( file.size ) ) {
			return;
		}

		wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_started', {
			type: key,
		} );

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', 'dispute_evidence' );

		// Set request status for UI.
		updateDispute( {
			...dispute,
			metadata: { ...dispute.metadata, [ key ]: '' },
			isUploading: { ...dispute.isUploading, [ key ]: true },
			uploadingErrors: { ...dispute.uploadingErrors, [ key ]: '' },
		} );

		// Force reload evidence components.
		updateEvidence( key, '' );

		try {
			const uploadedFile = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );
			// Store uploaded file name in metadata to display in submitted evidence or saved for later form.
			updateDispute( {
				...dispute,
				metadata: {
					...dispute.metadata,
					[ key ]: uploadedFile.filename,
				},
				isUploading: { ...dispute.isUploading, [ key ]: false },
				fileSize: { ...dispute.fileSize, [ key ]: uploadedFile.size },
			} );
			updateEvidence( key, uploadedFile.id );

			wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_success', {
				type: key,
			} );
		} catch ( err ) {
			wcpayTracks.recordEvent( 'wcpay_dispute_file_upload_failed', {
				message: err.message,
			} );

			updateDispute( {
				...dispute,
				metadata: { ...dispute.metadata, [ key ]: '' },
				isUploading: { ...dispute.isUploading, [ key ]: false },
				uploadingErrors: {
					...dispute.uploadingErrors,
					[ key ]: err.message,
				},
			} );

			// Force reload evidence components.
			updateEvidence( key, '' );
		}
	};

	const handleSaveSuccess = ( submit ) => {
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
		createSuccessNotice( message, {
			actions: [
				{
					label: submit
						? __(
								'View submitted evidence',
								'woocommerce-payments'
						  )
						: __(
								'Return to evidence submission',
								'woocommerce-payments'
						  ),
					url: addQueryArgs( 'admin.php', {
						page: 'wc-admin',
						path: '/payments/disputes/challenge',
						id: query.id,
					} ),
				},
			],
		} );

		getHistory().push( href );
	};

	const handleSaveError = ( err, submit ) => {
		wcpayTracks.recordEvent(
			submit
				? 'wcpay_dispute_submit_evidence_failed'
				: 'wcpay_dispute_save_evidence_failed'
		);

		const message = submit
			? __( 'Failed to submit evidence. (%s)', 'woocommerce-payments' )
			: __( 'Failed to save evidence. (%s)', 'woocommerce-payments' );
		createErrorNotice( sprintf( message, err.message ) );
	};

	const doSave = async ( submit ) => {
		// Prevent submit if upload is in progress.
		if ( isUploadingEvidence() ) {
			createInfoNotice(
				__(
					'Please wait until file upload is finished',
					'woocommerce-payments'
				)
			);
			return;
		}

		// setLoading( true );

		try {
			wcpayTracks.recordEvent(
				submit
					? 'wcpay_dispute_submit_evidence_clicked'
					: 'wcpay_dispute_save_evidence_clicked'
			);

			const { metadata } = dispute;
			const updatedDispute = await apiFetch( {
				path,
				method: 'post',
				data: {
					// Send full evidence, as submission does not appear to work without new evidence despite being optional.
					evidence: { ...dispute.evidence, ...evidence },
					metadata,
					submit,
				},
			} );
			handleSaveSuccess( submit );
			setEvidence( {} );
			updateDispute( updatedDispute );
		} catch ( err ) {
			handleSaveError( err, submit );
		} finally {
			// setLoading( false );
		}
	};

	const productType = getDisputeProductType( dispute );
	const updateProductType = ( newProductType ) => {
		const properties = {
			selection: newProductType,
		};
		wcpayTracks.recordEvent( 'wcpay_dispute_product_selected', properties );
		updateDispute( {
			...dispute,
			metadata: {
				...dispute.metadata,
				[ PRODUCT_TYPE_META_KEY ]: newProductType,
			},
		} );
	};

	const disputeReason = dispute && dispute.reason;
	const fieldsToDisplay = useMemo(
		() => evidenceFields( disputeReason, productType ),
		[ disputeReason, productType ]
	);

	return (
		<DisputeEvidencePage
			isLoading={ isLoading }
			dispute={ dispute }
			evidence={
				dispute
					? {
							...dispute.evidence,
							...evidence,
							metadata: dispute.metadata || {},
							isUploading: dispute.isUploading || {},
							uploadingErrors: dispute.uploadingErrors || {},
					  }
					: {}
			}
			onChange={ updateEvidence }
			onFileChange={ doUploadFile }
			onFileRemove={ doRemoveFile }
			onSave={ doSave }
			productType={ productType }
			onChangeProductType={ updateProductType }
			fields={ fieldsToDisplay }
		/>
	);
};
