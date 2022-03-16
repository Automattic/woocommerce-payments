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
import { FileUploadControl } from 'components/file-upload';

interface CardReaderFileUploadProps {
	fieldKey: string;
	label: string;
	accept: string;
	disabled?: boolean;
	help?: string;
	purpose: string;
	fileID: string;
	updateFileID: ( id: string ) => void;
}

const BrandingFileUpload: React.FunctionComponent< CardReaderFileUploadProps > = (
	props
) => {
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
	const [ uploadError, setUploadError ] = useState< boolean | string >(
		false
	);

	const { createErrorNotice } = useDispatch( 'core/notices' );

	const fileSizeExceeded = ( size: number ) => {
		const fileSizeLimitInBytes = 510000;
		if ( fileSizeLimitInBytes < size ) {
			createErrorNotice(
				__(
					'The file you have attached is exceeding the maximum limit.',
					'woocommerce-payments'
				)
			);

			return true;
		}
	};

	const handleFileChange = async ( key: string, file: File ) => {
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
		// Interpreting as_account as Boolean false in the backend
		body.append( 'as_account', '0' );

		try {
			const uploadedFile: unknown = await apiFetch( {
				path: '/wc/v3/payments/file',
				method: 'post',
				body,
			} );

			if ( uploadedFile ) {
				// Store uploaded file ID.
				updateFileID( ( uploadedFile as any ).id );
			}

			setLoading( false );
			setUploadError( false );

			wcpayTracks.recordEvent(
				'wcpay_merchant_settings_file_upload_success',
				{
					type: key,
				}
			);
		} catch ( { err } ) {
			wcpayTracks.recordEvent( 'wcpay_merchant_settings_upload_failed', {
				message: ( err as Error ).message,
			} );

			// Remove file ID
			updateFileID( '' );

			setLoading( false );
			setUploadError( ( err as Error ).message || '' );

			// Show error notice
			createErrorNotice( ( err as Error ).message );
		}
	};

	const handleFileRemove = () => {
		updateFileID( '' );

		setLoading( false );
		setUploadError( false );
	};

	const isDone = ( ! isLoading && fileID && 0 < fileID.length ) as boolean;
	const error = ( uploadError || '' ) as string;

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
				showPreview={ true }
			/>
		</div>
	);
};

export default BrandingFileUpload;
