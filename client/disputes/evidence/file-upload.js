/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BaseControl,
	DropZone,
	DropZoneProvider,
	FormFileUpload,
	Button,
} from '@wordpress/components';
import Gridicon from 'gridicons';

export const FileUploadControl = ( props ) => {
	const {
		fileName,
		field,
		disabled,
		isDone,
		isLoading,
		accept,
		error,
		onFileChange,
		onFileRemove,
		help,
	} = props;
	const hasError = error && 0 < error.length;
	const getIcon = () => {
		return (
			<Gridicon
				icon={ isDone && ! hasError ? 'checkmark' : 'add-outline' }
				size={ 18 }
			/>
		);
	};

	return (
		<BaseControl
			id={ `form-file-upload-base-control-${ field.key }` }
			label={ field.label }
			help={ help }
		>
			<DropZoneProvider>
				<DropZone
					onFilesDrop={ ( files ) =>
						onFileChange( field.key, files[ 0 ] )
					}
				/>
			</DropZoneProvider>
			<div className="file-upload">
				<FormFileUpload
					id={ `form-file-upload-${ field.key }` }
					className={ isDone && ! hasError ? 'is-success' : null }
					isSecondary
					isDestructive={ hasError }
					isBusy={ isLoading }
					disabled={ disabled || isLoading }
					icon={ getIcon() }
					accept={ accept }
					onChange={ ( event ) =>
						onFileChange( field.key, event.target.files[ 0 ] )
					}
				>
					{ __( 'Upload file', 'woocommerce-payments' ) }
				</FormFileUpload>

				{ hasError ? (
					<span className="upload-message is-destructive">
						{ error }
					</span>
				) : (
					<span className="upload-message">{ fileName }</span>
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
