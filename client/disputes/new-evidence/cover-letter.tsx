/**
 * External dependencies
 */
import React, { useEffect } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import {
	TextareaControl,
	Button,
	Icon,
} from 'wcpay/components/wp-components-wrapped';
import { Dispute } from 'wcpay/types/disputes';
import { Charge } from 'wcpay/types/charges';
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';

interface ExtendedDispute extends Omit< Dispute, 'evidence' | 'charge' > {
	merchant_name?: string;
	merchant_address?: string;
	merchant_email?: string;
	merchant_phone?: string;
	evidence: {
		[ key: string ]:
			| string
			| Record< string, boolean >
			| Record< string, string >;
	};
	charge: Charge;
}

interface CoverLetterProps {
	value: string;
	onChange: ( value: string ) => void;
	dispute: ExtendedDispute;
}

const CoverLetter: React.FC< CoverLetterProps > = ( {
	value,
	onChange,
	dispute,
} ) => {
	useEffect( () => {
		if ( ! dispute ) return;

		const generateCoverLetter = () => {
			const merchantName = dispute?.merchant_name || 'Your Business Name';
			const merchantAddress = dispute?.merchant_address || 'Your Address';
			const merchantEmail = dispute?.merchant_email || 'your@email.com';
			const merchantPhone =
				dispute?.merchant_phone || 'Your Phone Number';
			const today = new Date().toLocaleDateString( undefined, {
				year: 'numeric',
				month: 'long',
				day: 'numeric',
			} );
			const acquiringBank =
				dispute?.charge?.payment_method_details?.type ===
				PAYMENT_METHOD_IDS.CARD
					? dispute?.charge?.payment_method_details?.card?.network ||
					  'Acquiring Bank'
					: 'Acquiring Bank';
			const caseNumber = dispute?.id || 'Case Number';
			const transactionId = dispute?.charge?.id || 'Transaction ID';
			const transactionDate = dispute?.created
				? formatDateTimeFromTimestamp( dispute.created, {
						separator: ', ',
						includeTime: true,
				  } )
				: 'Transaction Date';
			const customerName =
				dispute?.charge?.billing_details?.name || 'Customer Name';
			const product = dispute?.evidence?.product_description || 'Product';
			const orderDate = dispute?.charge?.created
				? formatDateTimeFromTimestamp( dispute.charge.created, {
						separator: ', ',
						includeTime: true,
				  } )
				: 'Order Date';

			// Generate list of attachments based on provided evidence
			const attachments = [];
			let attachmentCount = 0;

			if ( dispute.evidence?.receipt ) {
				attachmentCount++;
				attachments.push(
					`• Order receipt (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.customer_communication ) {
				attachmentCount++;
				attachments.push(
					`• Customer communication (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.customer_signature ) {
				attachmentCount++;
				attachments.push(
					`• Customer signature (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.refund_policy ) {
				attachmentCount++;
				attachments.push(
					`• Store refund policy (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.shipping_documentation ) {
				attachmentCount++;
				attachments.push(
					`• Proof of shipping (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.service_documentation ) {
				attachmentCount++;
				attachments.push(
					`• Service documentation (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.cancellation_policy ) {
				attachmentCount++;
				attachments.push(
					`• Cancellation policy (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.access_activity_log ) {
				attachmentCount++;
				attachments.push(
					`• Access activity log (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}
			if ( dispute.evidence?.uncategorized_file ) {
				attachmentCount++;
				attachments.push(
					`• Additional documentation (Attachment ${ String.fromCharCode(
						64 + attachmentCount
					) })`
				);
			}

			// If no attachments were provided, use default list
			const attachmentsList =
				attachments.length > 0
					? attachments.join( '\n' )
					: `• AVS/CVV Match: Billing address and security code matched (Attachment A)
• IP/Device Data: Location and device info used at purchase (Attachment B)
• Customer Confirmation: Email or chat confirming purchase (Attachment C)
• Usage Data: Login records for the digital goods (Attachment D)`;

			return `${ merchantName }
${ merchantAddress }
${ merchantEmail }
${ merchantPhone }
${ today }

To: ${ acquiringBank }
Subject: Chargeback Dispute – Case # ${ caseNumber }

Dear Dispute Resolution Team,

We are submitting evidence in response to chargeback #${ caseNumber } for transaction #${ transactionId } on ${ transactionDate }.

Our records indicate that the customer and legitimate cardholder, ${ customerName }, ordered ${ product } on ${ orderDate }.

To support our case, we are providing the following documentation:
${ attachmentsList }

Based on this information, we respectfully request that the chargeback be reversed. Please let me know if any further details are required.

Thank you,
${ merchantName }`;
		};

		onChange( generateCoverLetter() );
	}, [ dispute, onChange ] );

	const handleViewCoverLetter = () => {
		const htmlContent = `
			<!DOCTYPE html>
			<html>
			<head>
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
			/>
			<Button
				className="wcpay-dispute-evidence-cover-letter__print"
				variant="primary"
				onClick={ handleViewCoverLetter }
				icon={ <Icon icon={ external } size={ 24 } /> }
			>
				{ __( 'View cover letter', 'woocommerce-payments' ) }
			</Button>
		</section>
	);
};

export default CoverLetter;
