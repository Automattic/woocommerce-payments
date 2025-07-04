/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import FileUploadControl from './file-upload-control';
import { DocumentField, RecommendedDocumentsProps } from './types';

const RecommendedDocuments: React.FC< RecommendedDocumentsProps > = ( {
	fields,
	readOnly = false,
} ) => {
	return (
		<section className="wcpay-dispute-evidence-recommended-documents">
			<h3 className="wcpay-dispute-evidence-recommended-documents__heading">
				{ __( 'Recommended documents', 'woocommerce-payments' ) }
			</h3>
			<div className="wcpay-dispute-evidence-recommended-documents__subheading">
				{ __(
					'While optional, we strongly recommend providing as many of these documents as possible. The following file types are supported: PDF, JPEG, and PNG.',
					'woocommerce-payments'
				) }
			</div>
			<ul className="wcpay-dispute-evidence-recommended-documents__list">
				{ fields.map( ( field: DocumentField ) => (
					<li
						key={ field.key }
						className="wcpay-dispute-evidence-recommended-documents__item"
					>
						<FileUploadControl
							label={ field.label }
							fileName={ field.fileName || '' }
							fileSize={ field.fileSize }
							description={ field.description }
							onFileChange={ async ( file: File ) =>
								field.onFileChange( field.key, file )
							}
							onFileRemove={ async () => field.onFileRemove() }
							disabled={ readOnly || field.readOnly }
							isDone={ !! field.uploaded }
							isBusy={ field.isBusy }
							accept={ '.pdf, image/png, image/jpeg' }
						/>
					</li>
				) ) }
			</ul>
		</section>
	);
};

export default RecommendedDocuments;
