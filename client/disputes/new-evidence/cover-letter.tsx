/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { TextareaControl } from 'wcpay/components/wp-components-wrapped/components/textarea-control';
import { Button } from 'wcpay/components/wp-components-wrapped/components/button';
import type { CoverLetterProps } from './types';

const CoverLetter: React.FC< CoverLetterProps > = ( {
	value,
	onChange,
	readOnly = false,
} ) => {
	const handleViewCoverLetter = () => {
		const htmlContent = `
			<!DOCTYPE html>
			<html>
			<head>
				<meta charset="UTF-8">
				<title>${ __( 'Cover Letter', 'woocommerce-payments' ) }</title>
				<style>
					body {
						font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
						line-height: 1.6;
						max-width: 120ch;
						margin: 40px auto;
						padding: 20px;
						text-align: justify;
					}
					pre {
						white-space: pre-wrap;
						word-wrap: break-word;
						word-break: break-word;
						overflow-wrap: break-word;
						max-width: 100%;
					}
					@media print {
						body {
							margin: 0;
							padding: 20px;
							font-size: 12px;
						}
						pre {
							font-size: 12px;
						}
						.no-print {
							display: none;
						}
					}
					.print-button-container {
						position: fixed;
						bottom: 20px;
						left: 50%;
						transform: translateX(-50%);
						background: white;
						padding: 10px;
						border-radius: 4px;
						box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
					}
					@media print {
						.print-button-container {
							display: none;
						}
					}
				</style>
			</head>
			<body>
				<pre>${ value }</pre>
				<div class="print-button-container no-print">
					<button onclick="window.print()" style="
						padding: 8px 16px;
						background: #3B5AFB;
						color: white;
						border: none;
						border-radius: 4px;
						cursor: pointer;
					">
						${ __( 'Print Cover Letter', 'woocommerce-payments' ) }
					</button>
				</div>
			</body>
			</html>
		`;

		const blob = new Blob( [ htmlContent ], { type: 'text/html' } );
		const url = URL.createObjectURL( blob );
		const printWindow = window.open( url, '_blank' );

		// Clean up the blob URL after the window loads
		if ( printWindow ) {
			printWindow.onload = () => {
				URL.revokeObjectURL( url );
			};
		}
	};

	return (
		<section className="wcpay-dispute-evidence-cover-letter">
			<TextareaControl
				label={ __( 'COVER LETTER', 'woocommerce-payments' ) }
				value={ value }
				onChange={ onChange }
				rows={ 30 }
				className="wcpay-dispute-evidence-cover-letter__textarea"
				readOnly={ readOnly }
				__nextHasNoMarginBottom
			/>
			<Button
				className="wcpay-dispute-evidence-cover-letter__print"
				variant="primary"
				onClick={ handleViewCoverLetter }
				iconPosition="right"
				iconSize={ 24 }
				icon={ external }
				__next40pxDefaultSize
			>
				{ __( 'Preview cover letter', 'woocommerce-payments' ) }
			</Button>
		</section>
	);
};

export default CoverLetter;
