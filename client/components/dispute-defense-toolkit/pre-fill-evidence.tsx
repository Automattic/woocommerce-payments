/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { recordEvent } from 'tracks';

interface PreFillEvidenceProps {
	disputeReason: string;
	productType: string;
	onPreFill: ( evidence: Record< string, string > ) => void;
}

/**
 * Component that provides pre-filled evidence data based on dispute reason and product type
 */
const PreFillEvidence: React.FC< PreFillEvidenceProps > = ( {
	disputeReason,
	productType,
	onPreFill,
} ) => {
	// Hard-coded evidence data for demonstration purposes
	const getPreFilledEvidence = () => {
		// General evidence that applies to all dispute types
		const generalEvidence: Record< string, string > = {
			product_description:
				'Premium digital subscription service with monthly billing',
			customer_name: 'John Doe',
			customer_email_address: 'john.doe@example.com',
			billing_address: '123 Main St, Anytown, CA 90210',
			customer_purchase_ip: '192.168.1.1',
			customer_communication:
				'Customer confirmed receipt of service via email on 2023-05-15',
		};

		// Evidence specific to dispute reason and product type
		let specificEvidence: Record< string, string > = {};

		// Handle different dispute reasons
		switch ( disputeReason ) {
			case 'fraudulent':
				if ( productType === 'digital_product_or_service' ) {
					specificEvidence = {
						access_activity_log:
							'Customer accessed the service on 2023-05-10, 2023-05-12, and 2023-05-15',
					};
				} else if ( productType === 'physical_product' ) {
					specificEvidence = {
						shipping_carrier: 'USPS',
						shipping_tracking_number: '9400100897654321',
						shipping_date: '2023-05-05',
						shipping_address: '123 Main St, Anytown, CA 90210',
					};
				} else if ( productType === 'offline_service' ) {
					specificEvidence = {
						service_date: '2023-05-10',
						service_documentation:
							'Service was provided as scheduled',
					};
				}
				break;
			case 'product_not_received':
				if ( productType === 'physical_product' ) {
					specificEvidence = {
						shipping_carrier: 'USPS',
						shipping_tracking_number: '9400100897654321',
						shipping_date: '2023-05-05',
						shipping_address: '123 Main St, Anytown, CA 90210',
					};
				} else if ( productType === 'digital_product_or_service' ) {
					specificEvidence = {
						access_activity_log:
							'Customer accessed the service on 2023-05-10, 2023-05-12, and 2023-05-15',
					};
				} else if ( productType === 'offline_service' ) {
					specificEvidence = {
						service_date: '2023-05-10',
						service_documentation:
							'Service was provided as scheduled',
					};
				}
				break;
			case 'credit_not_processed':
				specificEvidence = {
					refund_policy:
						'Our refund policy allows for refunds within 30 days of purchase',
					refund_policy_disclosure:
						'The refund policy was clearly displayed during checkout',
					refund_refusal_explanation:
						'The customer is not entitled to a refund as they have used the service for more than 30 days',
				};
				break;
			case 'duplicate':
				specificEvidence = {
					duplicate_charge_id: 'ch_1234567890',
					duplicate_charge_explanation:
						'This is a separate charge for a different product',
				};
				break;
			case 'subscription_canceled':
				specificEvidence = {
					cancellation_policy:
						'Our cancellation policy requires 30 days notice',
					cancellation_policy_disclosure:
						'The cancellation policy was clearly displayed during checkout',
					cancellation_rebuttal:
						'The customer did not provide the required 30 days notice for cancellation',
				};
				break;
			default:
				// For unrecognized or other dispute reasons
				specificEvidence = {
					uncategorized_text:
						'This transaction was legitimate and the customer received the product/service as described',
				};
		}

		return { ...generalEvidence, ...specificEvidence };
	};

	const handlePreFill = () => {
		const preFilledEvidence = getPreFilledEvidence();
		onPreFill( preFilledEvidence );

		recordEvent( 'wcpay_dispute_prefill_evidence_clicked', {
			dispute_reason: disputeReason,
			product_type: productType,
		} );
	};

	return (
		<Button
			variant="secondary"
			onClick={ handlePreFill }
			className="dispute-defense-toolkit__prefill-button"
		>
			{ __( 'Pre-fill evidence form', 'woocommerce-payments' ) }
		</Button>
	);
};

export default PreFillEvidence;
