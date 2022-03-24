/**
 * External dependencies
 */
import * as React from 'react';
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	DropZone,
	DropZoneProvider,
	FormFileUpload,
	Button,
} from '@wordpress/components';
import Gridicon from 'gridicons';

/**
 * Internal dependencies.
 */
import type { DisputeFileUpload } from 'wcpay/types/disputes';
import FileUploadError from './upload-error';
import FileUploadPreview from './preview';

export const FileUploadControl = ( {
	field,
	fileName,
	disabled,
	isDone,
	isLoading,
	accept,
	error,
	onFileChange,
	onFileRemove,
	help,
	showPreview,
}: DisputeFileUpload ): JSX.Element => {
	const hasError = ( error && 0 < error.length ) || false;

	const getIcon = (
		<Gridicon
			icon={ isDone && ! hasError ? 'checkmark' : 'add-outline' }
			size={ 18 }
		/>
	);

	const handleButtonClick = (
		event: React.MouseEvent< HTMLButtonElement >,
		openFileDialog: () => void
	) => {
		// Get file input next to the button element and clear it's value, allowing to select the same file again in case of connection or general error or need to select it again
		const fileInput:
			| HTMLInputElement
			| null
			| undefined = ( event.target as HTMLButtonElement )
			.closest( '.components-form-file-upload' )
			?.querySelector( 'input[type="file"]' );

		if ( fileInput ) {
			fileInput.value = '';
		}

		openFileDialog();
	};

	return (
		<BaseControl
			id={ `form-file-upload-base-control-${ field.key }` }
			label={ field.label }
			help={ help }
		>
			<DropZoneProvider>
				<DropZone
					onFilesDrop={ ( files: Array< File > ) =>
						onFileChange( field.key, files[ 0 ] )
					}
				/>
			</DropZoneProvider>
			<div className="file-upload">
				<FormFileUpload
					accept={ accept }
					onChange={ (
						event: React.ChangeEvent< HTMLInputElement >
					): void => {
						onFileChange(
							field.key,
							( event.target.files || new FileList() )[ 0 ]
						);
					} }
					render={ ( { openFileDialog } ) => (
						<Button
							id={ `form-file-upload-${ field.key }` }
							className={
								isDone && ! hasError ? 'is-success' : ''
							}
							isSecondary
							isDestructive={ hasError }
							isBusy={ isLoading }
							disabled={ disabled || isLoading }
							icon={ getIcon }
							onClick={ (
								event: React.MouseEvent< HTMLButtonElement >
							) => handleButtonClick( event, openFileDialog ) }
						>
							{ __( 'Upload file', 'woocommerce-payments' ) }
						</Button>
					) }
				></FormFileUpload>

				{ hasError ? (
					<FileUploadError error={ error } />
				) : (
					<FileUploadPreview
						fileName={ fileName }
						showPreview={ showPreview }
					/>
				) }

				{ isDone && ! disabled ? (
					<Button
						className="delete-uploaded-file-button"
						aria-label={ __(
							'Remove file',
							'woocommerce-payments'
						) }
						icon={ <Gridicon icon="trash" size={ 18 } /> }
						onClick={ () => onFileRemove( field.key ) }
					/>
				) : null }
			</div>
		</BaseControl>
	);
};
