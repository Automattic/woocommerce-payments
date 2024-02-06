/** @format */
/**
 * External dependencies
 */
import React from 'react';
import { recordEvent, events } from 'tracks';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { BaseControl, Button } from '@wordpress/components';
import TrashIcon from 'gridicons/dist/trash';
import classNames from 'classnames';

/**
 * Internal dependencies
 */
import { FileUploadControl } from 'components/file-upload';

interface WooPayFileUploadProps {
	fieldKey: string;
	label: string;
	accept: string;
	disabled?: boolean;
	help?: string;
	purpose: string;
	fileID: string;
	updateFileID: ( id: string ) => void;
}

const WooPayFileUpload: React.FunctionComponent< WooPayFileUploadProps > = (
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

		recordEvent( events.SETTINGS_FILE_UPLOAD_STARTED, {
			type: key,
		} );

		const body = new FormData();
		body.append( 'file', file );
		body.append( 'purpose', purpose );

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

			recordEvent( events.SETTINGS_FILE_UPLOAD_SUCCESS, {
				type: key,
			} );
		} catch ( { err } ) {
			recordEvent( events.SETTINGS_FILE_UPLOAD_FAILED, {
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

	const openFileDialog = ( event: React.MouseEvent< HTMLButtonElement > ) => {
		const fileInput:
			| HTMLInputElement
			| null
			| undefined = ( event.target as HTMLButtonElement )
			.closest( '.woopay-settings__update-store-logo' )
			?.querySelector( 'input[type="file"]' );

		fileInput?.click();
	};

	const isDone = ( ! isLoading && fileID && 0 < fileID.length ) as boolean;
	const error = ( uploadError || '' ) as string;

	return (
		<div className="wcpay-branding-upload-field__wrapper">
			<div
				className={ classNames(
					'woopay-settings__update-store-logo',
					fileID && 'has-file'
				) }
			>
				<FileUploadControl
					field={ {
						key: fieldKey,
						label: label,
					} }
					fileName={ fileID }
					isLoading={ isLoading }
					accept={ accept }
					disabled={ disabled }
					isDone={ false }
					error={ error }
					onFileChange={ handleFileChange }
					onFileRemove={ handleFileRemove }
					showPreview={ true }
					type="image"
					uploadButtonLabel={ __(
						'Upload custom logo',
						'woocommerce-payments'
					) }
				/>

				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
					} }
				>
					{ isDone && (
						<>
							<Button onClick={ openFileDialog } isLink>
								{ __( 'Replace', 'woocommerce-payments' ) }
							</Button>
							<Button
								className="delete-uploaded-file-button"
								aria-label={ __(
									'Remove file',
									'woocommerce-payments'
								) }
								icon={ <TrashIcon size={ 18 } /> }
								onClick={ handleFileRemove }
							/>
						</>
					) }
				</div>
			</div>

			<BaseControl id={ 'test' } help={ help }>
				{ ' ' }
			</BaseControl>
		</div>
	);
};

export default WooPayFileUpload;
