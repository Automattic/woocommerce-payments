/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { check, close, cloudUpload } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import {
	Button,
	FormFileUpload,
	Icon,
} from 'wcpay/components/wp-components-wrapped';

interface FileUploadControlProps {
	fileName?: string;
	onFileChange: ( file: File ) => void;
	onFileRemove: () => void;
	disabled?: boolean;
	isDone?: boolean;
	isBusy?: boolean;
	accept?: string;
	label: string;
}

const FileUploadControl: React.FC< FileUploadControlProps > = ( {
	fileName = '',
	onFileChange,
	onFileRemove,
	disabled = false,
	isDone = false,
	isBusy = false,
	accept = '.pdf, image/png, image/jpeg',
	label,
} ) => {
	return (
		<div className="wcpay-dispute-evidence-file-upload-control">
			{ isDone && fileName ? <Icon icon={ check } size={ 48 } /> : null }
			<label className="wcpay-dispute-evidence-file-upload-control__label">
				{ label }
			</label>
			{ isDone && fileName ? (
				<span
					className="wcpay-dispute-evidence-file-upload-control__filename"
					title={ fileName }
				>
					<span className="wcpay-dispute-evidence-file-upload-control__filename-text">
						{ fileName }
					</span>
					<Button
						className="wcpay-dispute-evidence-file-upload-control__remove"
						icon={ <Icon icon={ close } size={ 24 } /> }
						onClick={ onFileRemove }
						disabled={ disabled }
						aria-label={ __(
							'Remove file',
							'woocommerce-payments'
						) }
						variant="tertiary"
					/>
				</span>
			) : null }
			<div className="wcpay-dispute-evidence-file-upload-control__actions">
				<FormFileUpload
					accept={ accept }
					onChange={ (
						event: React.ChangeEvent< HTMLInputElement >
					) => {
						if ( event.target.files && event.target.files[ 0 ] ) {
							onFileChange( event.target.files[ 0 ] );
							event.target.value = '';
						}
					} }
					render={ ( { openFileDialog } ) => (
						<Button
							className="wcpay-dispute-evidence-file-upload-control__upload"
							icon={ <Icon icon={ cloudUpload } size={ 24 } /> }
							onClick={ openFileDialog }
							disabled={ disabled || isBusy }
							isBusy={ isBusy }
							aria-label={ __(
								'Upload file',
								'woocommerce-payments'
							) }
							variant="primary"
						/>
					) }
				/>
			</div>
		</div>
	);
};

export default FileUploadControl;
