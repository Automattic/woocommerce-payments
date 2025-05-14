/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

const PdfPreview = ( {} ) => {
	// For development purposes, we'll use a placeholder PDF
	// In production, this would be replaced with the actual generated PDF URL
	const pdfUrl = 'http://localhost:8082/wp-content/uploads/2025/05/dummy.pdf';

	return (
		<div className="pdf-preview">
			<div className="pdf-preview-container">
				<object
					data={ pdfUrl }
					type="application/pdf"
					className="pdf-preview-frame"
					aria-label={ __(
						'Evidence PDF Preview',
						'woocommerce-payments'
					) }
				>
					<div className="pdf-preview-fallback">
						<p>
							{ __(
								'Your browser does not support PDF preview. Please download the PDF to view it.',
								'woocommerce-payments'
							) }
						</p>
						<Button
							variant="secondary"
							href={ pdfUrl }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ __( 'Download PDF', 'woocommerce-payments' ) }
						</Button>
					</div>
				</object>
			</div>
		</div>
	);
};

export default PdfPreview;
