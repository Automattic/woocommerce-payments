/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { BaseControl, FormFileUpload, IconButton } from '@wordpress/components';
import Gridicon from 'gridicons';

export const FileUploadControl = ( props ) => {
	const { fileName, field, isDone, isLoading, accept, error, onFileChange, onFileRemove } = props;
	const hasError = error && error.length > 0;
	const getIcon = () => {
		return <Gridicon
					icon={ isDone && ! hasError ? 'checkmark' : 'add-outline' }
					size={ 18 } />;
	};

	const message = hasError ? error : fileName;
	const messageClass = hasError ? 'upload-message is-destructive' : 'upload-message';

	return (
		<BaseControl
			id={ `form-file-upload-base-control-${ field.key }` }
			label={ field.display }
			help={ field.description }
		>
			<div className={ 'file-upload' }>
				<FormFileUpload
					id={ `form-file-upload-${ field.key }` }
					className={ isDone && ! hasError ? 'is-success' : null }
					isLarge
					isPrimary
					isDestructive={ hasError }
					isBusy={ isLoading }
					disabled={ isLoading }
					icon={ getIcon() }
					accept={ accept }
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
