/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { closeSmall, cloudUpload } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { Button, FormFileUpload } from '@wordpress/components';
import { FileUploadControlProps } from './types';
import { formatFileNameWithSize } from './utils';
import { useViewport } from 'wcpay/hooks/use-viewport';

const FileUploadControl: React.FC< FileUploadControlProps > = ( {
	fileName = '',
	fileSize,
	description,
	onFileChange,
	onFileRemove,
	disabled = false,
	isDone = false,
	isBusy = false,
	accept = '.pdf, image/png, image/jpeg',
	label,
} ) => {
	const { isVerySmallMobile } = useViewport();

	// Render file chip component
	const renderFileChip = () => {
		if ( ! isDone || ! fileName ) {
			return null;
		}

		return (
			<div className="wcpay-dispute-evidence-file-upload-control__chip">
				<span className="wcpay-dispute-evidence-file-upload-control__chip-filename">
					{ fileSize ? (
						<>
							<div className="wcpay-dispute-evidence-file-upload-control__chip-filename-name">
								{
									formatFileNameWithSize( fileName, fileSize )
										.namePart
								}
							</div>
							<div className="wcpay-dispute-evidence-file-upload-control__chip-filename-extension">
								{
									formatFileNameWithSize( fileName, fileSize )
										.extensionSizePart
								}
							</div>
						</>
					) : (
						fileName
					) }
				</span>
				<Button
					className="wcpay-dispute-evidence-file-upload-control__chip-action"
					icon={ closeSmall }
					onClick={ onFileRemove }
					disabled={ disabled }
					aria-label={ __( 'Remove file', 'woocommerce-payments' ) }
					variant="tertiary"
				/>
			</div>
		);
	};

	return (
		<div className="wcpay-dispute-evidence-file-upload-control">
			<div className="wcpay-dispute-evidence-file-upload-control__info">
				<div className="wcpay-dispute-evidence-file-upload-control__info-header">
					<label className="wcpay-dispute-evidence-file-upload-control__label">
						{ label }
					</label>
					{ /* Only show chip in header for larger screens */ }
					{ ! isVerySmallMobile && renderFileChip() }
				</div>
				{ description && (
					<p className="wcpay-dispute-evidence-file-upload-control__info-description">
						{ description }
					</p>
				) }
				{ /* Show chip after description for very small mobile screens */ }
				{ isVerySmallMobile && renderFileChip() }
			</div>
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
							icon={ cloudUpload }
							iconSize={ 24 }
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
