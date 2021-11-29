/** @format */
/**
 * External dependencies
 */
import React from 'react';
import wcpayTracks from 'tracks';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { FileUploadControl } from 'wcpay/disputes/evidence/file-upload';

const BrandingFileUpload = ( props ) => {
	const {
		fieldKey,
		label,
		accept,
		disabled,
		help,
		purpose,
		fileID,
		updateFileID,
	} = props;

	const [ isLoading, setLoading ] = useState( false );
	const [ uploadError, setUploadError ] = useState( false );

	const { createErrorNotice } = useDispatch( 'core/notices' );

	const fileSizeExceeded = ( fileSize ) => {
		const fileSizeLimitInBytes = 510000;
		if ( fileSizeLimitInBytes < fileSize ) {
			createErrorNotice(
				__(
					'The file you have attached is exceeding the maximum limit.',
					'woocommerce-payments'
				)
			);

			return true;
		}
	};

	const handleFileChange = async ( key, file ) => {
		if ( ! file ) {
			return;
		}

		if ( fileSizeExceeded( file.size ) ) {
			return;
		}

		setLoading( true );

		wcpayTracks.recordEvent(
			'wcpay_merchant_settings_file_upload_started',
			{
				type: key,
			}
		);

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', purpose );

		try {
			const uploadedFile = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );

			// Store uploaded file ID.
			updateFileID( uploadedFile.id );

			setLoading( false );
			setUploadError( false );

			wcpayTracks.recordEvent(
				'wcpay_merchant_settings_file_upload_success',
				{
					type: key,
				}
			);
		} catch ( err ) {
			wcpayTracks.recordEvent( 'wcpay_merchant_settings_upload_failed', {
				message: err.message,
			} );

			// Remove file ID
			updateFileID( '' );

			setLoading( false );
			setUploadError( err.message );

			// Show error notice
			createErrorNotice( err.message );
		}
	};

	const handleFileRemove = () => {
		updateFileID( '' );

		setLoading( false );
		setUploadError( false );
	};

	const isDone = ! isLoading && fileID && 0 < fileID.length;
	const error = uploadError || '';

	return (
		<div className="wcpay-branding-upload-field__wrapper">
			<FileUploadControl
				field={ {
					key: fieldKey,
					label: label,
				} }
				fileName={ fileID }
				isLoading={ isLoading }
				accept={ accept }
				disabled={ disabled }
				isDone={ isDone }
				help={ help }
				error={ error }
				onFileChange={ handleFileChange }
				onFileRemove={ handleFileRemove }
			/>
		</div>
	);
};

export default BrandingFileUpload;
