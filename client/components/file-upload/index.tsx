/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import CheckmarkIcon from 'gridicons/dist/checkmark';
import ImageIcon from 'gridicons/dist/image';
import AddOutlineIcon from 'gridicons/dist/add-outline';
import TrashIcon from 'gridicons/dist/trash';
import { DropZone, FormFileUpload, Button } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import FileUploadError from './upload-error';
import FileUploadPreview from './preview';

interface FileUploadControlProps {
	fieldKey: string;
	fileName: string;
	disabled?: boolean;
	isDone: boolean;
	isLoading: boolean;
	accept: string;
	error?: string;
	onFileChange( key: string, file: File ): Promise< void >;
	onFileRemove( key: string ): void;
	help?: string;
	showPreview?: boolean;
	uploadButtonLabel?: string;
	type?: string;
}

export const FileUploadControl = ( {
	fieldKey,
	fileName,
	disabled,
	isDone,
	isLoading,
	accept,
	error,
	onFileChange,
	onFileRemove,
	showPreview,
	uploadButtonLabel,
	type = 'file',
}: FileUploadControlProps ): JSX.Element => {
	const hasError = ( error && 0 < error.length ) || false;

	const IconType = type === 'image' ? ImageIcon : AddOutlineIcon;
	const Icon = isDone && ! hasError ? CheckmarkIcon : IconType;

	const handleButtonClick = (
		event: React.MouseEvent< HTMLButtonElement >,
		openFileDialog: () => void
	) => {
		// Get file input next to the button element and clear it's value,
		// allowing to select the same file again in case of
		// connection or general error or just need to select it again.
		// This workaround is useful until we update @wordpress/components to a
		// version the supports this: https://github.com/WordPress/gutenberg/issues/39267
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
		<>
			<DropZone
				onFilesDrop={ ( files: Array< File > ) =>
					onFileChange( fieldKey, files[ 0 ] )
				}
			/>
			<div className="file-upload">
				<FormFileUpload
					accept={ accept }
					onChange={ (
						event: React.ChangeEvent< HTMLInputElement >
					): void => {
						onFileChange(
							fieldKey,
							( event.target.files || new FileList() )[ 0 ]
						);
					} }
					render={ ( { openFileDialog } ) => (
						<Button
							id={ `form-file-upload-${ fieldKey }` }
							className={
								isDone && ! hasError ? 'is-success' : ''
							}
							isSecondary
							isDestructive={ hasError }
							isBusy={ isLoading }
							disabled={ disabled || isLoading }
							icon={ <Icon size={ 18 } /> }
							onClick={ (
								event: React.MouseEvent< HTMLButtonElement >
							) => handleButtonClick( event, openFileDialog ) }
						>
							{ uploadButtonLabel ||
								__( 'Upload file', 'woocommerce-payments' ) }
						</Button>
					) }
				/>

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
						icon={ <TrashIcon size={ 18 } /> }
						onClick={ () => onFileRemove( fieldKey ) }
					/>
				) : null }
			</div>
		</>
	);
};
