/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl, FormFileUpload, IconButton } from '@wordpress/components';
import Gridicon from 'gridicons';

export const FileUploadControl = ( evidence, field, onFileChange, onFileRemove ) => {
	const fileName = ( evidence.metadata && evidence.metadata[ field.key ] ) || '';
	const isDone = 0 !== fileName.length;
	const isLoading = evidence.isUploading && ( evidence.isUploading[ field.key ] || false );
	const hasError = evidence.uploadingErrors && ( evidence.uploadingErrors[ field.key ] || false );

	const getIcon = () => {
		return <Gridicon
					icon={ isDone && ! hasError ? 'checkmark' : 'add-outline' }
					size={ 18 } />;
	};

	const message = hasError ? __( 'Upload failed.', 'woocommerce-payments' ) : fileName;
	const messageClass = hasError ? 'is-destructive' : null;

	return (
		<BaseControl
			key={ field.key }
			id={ 'form-file-upload-base-control-' + field.key }
			label={ field.display }
			help={ field.description }
		>
			<div className={ 'file-upload' }>
				<FormFileUpload
					id={ 'form-file-upload-' + field.key }
					className={ isDone && ! hasError ? 'is-success' : null }
					isLarge
					isPrimary
					isDestructive={ hasError }
					isBusy={ isLoading }
					disabled={ isLoading }
					icon={ getIcon() }
					accept=".pdf, image/png, image/jpeg"
					onChange={ ( event ) => onFileChange( field.key, event.target.files[ 0 ] ) }
				>
					{ __( 'Upload File', 'woocommerce-payments' ) }
				</FormFileUpload>

				<span className={ messageClass }>{ message }</span>

				{ isDone
					? <IconButton
						className={ 'delete-uploaded-file-button' }
						icon={ <Gridicon icon="trash" size={ 18 } /> }
						onClick={ () => onFileRemove( field.key ) } />
					: null }
			</div>
		</BaseControl>
	);
};
