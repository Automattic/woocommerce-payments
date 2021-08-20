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
 * Internal dependencies
 */
import type { Field } from './fields';

// Fix the FormFileUpload props.
declare module '@wordpress/components' {
	// eslint-disable-next-line @typescript-eslint/no-namespace, no-shadow
	namespace FormFileUpload {
		interface IconButtonProps {
			id: string;
			className?: string;
			isPrimary?: boolean;
			isDestructive?: boolean;
			isBusy?: boolean;
			disabled?: boolean;
			icon?: JSX.Element;
			accept?: string;
		}
	}
}

type FileUploadProps = {
	fileName: string;
	field: Field;
	disabled: boolean;
	isDone: boolean;
	isLoading: boolean;
	accept?: string;
	error?: string;
	onFileChange: ( key: string, file?: Blob ) => void;
	onFileRemove: ( key: string ) => void;
	help: React.ReactNode;
};

export const FileUploadControl = ( props: FileUploadProps ): JSX.Element => {
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
	const hasError = undefined !== error && 0 < error.length;
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
					className={
						isDone && ! hasError ? 'is-success' : undefined
					}
					isPrimary
					isDestructive={ hasError }
					isBusy={ isLoading }
					disabled={ disabled || isLoading }
					icon={ getIcon() }
					accept={ accept }
					onChange={ ( event ) =>
						onFileChange( field.key, event?.target?.files?.[ 0 ] )
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
