/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { formatDateTimeFromTimestamp } from 'wcpay/utils/date-time';
import {
	formatMerchantAddress,
	formatDeliveryDate,
	generateAttachments,
	generateHeader,
	generateRecipient,
	generateBody,
	generateClosing,
	generateCoverLetter,
} from '../cover-letter-generator';
import type {
	ExtendedDispute,
	AccountDetails,
	CoverLetterData,
} from '../types';
import type { DisputeReason } from 'wcpay/types/disputes';
import PAYMENT_METHOD_IDS from '../../../constants/payment-method';

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromTimestamp: jest.fn(
		( timestamp ) => `Formatted ${ timestamp }`
	),
} ) );

describe( 'Cover Letter Generator', () => {
	const mockAccountDetails: AccountDetails = {
		name: 'Test Store',
		support_address_line1: '123 Main St',
		support_address_line2: 'Suite 100',
		support_address_city: 'Test City',
		support_address_state: 'TS',
		support_address_postal_code: '12345',
		support_address_country: 'US',
	};

	const mockDispute: ExtendedDispute = {
		id: 'dp_123',
		status: 'needs_response',
		evidence_details: {
			has_evidence: true,
			due_by: 1234567890,
			past_due: false,
			submission_count: 1,
		},
		metadata: {},
		order: null,
		issuer_evidence: null,
		fileSize: {},
		amount: 1000,
		currency: 'usd',
		balance_transactions: [],
		payment_intent: 'pi_123',
		reason: 'product_not_received',
		created: 1234567890,
		evidence: {
			receipt: 'receipt_url',
			shipping_documentation: 'shipping_url',
			uncategorized_file: 'delivery_url',
			product_description: 'Test Product',
			shipping_date: '2024-03-20',
		},
		charge: {
			id: 'ch_123',
			created: 1234567890,
			amount: 1000,
			amount_captured: 1000,
			amount_refunded: 0,
			application_fee_amount: 0,
			balance_transaction: {
				currency: 'usd',
				amount: 1000,
				fee: 100,
				reporting_category: 'dispute',
			},
			billing_details: {
				name: 'John Doe',
				email: 'john@example.com',
				phone: '123-456-7890',
				address: {
					line1: '123 Main St',
					line2: 'Apt 1',
					city: 'Test City',
					state: 'TS',
					postal_code: '12345',
					country: 'US',
				},
			},
			captured: true,
			level3: {
				line_items: [
					{
						product_description: 'Test Product',
						product_name: 'Test Product',
						quantity: 1,
						unit_cost: 1000,
					},
				],
			},
			outcome: null,
			paid: true,
			payment_method: 'card',
			payment_method_details: {
				type: PAYMENT_METHOD_IDS.CARD,
				card: {},
			},
			refunded: false,
			refunds: {
				data: [
					{
						balance_transaction: {
							currency: 'usd',
							amount: 0,
							fee: 0,
						},
					},
				],
			},
			status: 'succeeded',
			disputed: false,
			paydown: null,
			currency: 'usd',
			order: null,
			payment_intent: 'pi_123',
		},
	};

	const mockSettings = {
		account_business_support_email: 'test@example.com',
		account_business_support_phone: '123-456-7890',
	};

	const mockCoverLetterData: CoverLetterData = {
		merchantName: 'Test Store',
		merchantAddress: '123 Main St, Suite 100, Test City, TS 12345 US',
		merchantEmail: 'test@example.com',
		merchantPhone: '123-456-7890',
		today: 'March 20, 2024',
		acquiringBank: 'Test Bank',
		caseNumber: 'dp_123',
		transactionId: 'ch_123',
		transactionDate: 'March 20, 2024',
		customerName: 'John Doe',
		product: 'Test Product',
		orderDate: 'March 19, 2024',
		deliveryDate: 'March 20, 2024',
	};

	describe( 'formatMerchantAddress', () => {
		const originalWcpaySettings = ( window as any ).wcpaySettings;

		afterEach( () => {
			( window as any ).wcpaySettings = originalWcpaySettings;
		} );

		it( 'should use server-formatted address when available', () => {
			( window as any ).wcpaySettings = {
				...originalWcpaySettings,
				formattedStoreAddress:
					'123 Main St, Suite 100, Test City, TS 12345, United States (US)',
			};
			const result = formatMerchantAddress( mockAccountDetails );
			expect( result ).toBe(
				'123 Main St, Suite 100, Test City, TS 12345, United States (US)'
			);
		} );

		it( 'should fall back to client-side formatting', () => {
			const result = formatMerchantAddress( mockAccountDetails );
			expect( result ).toBe(
				'123 Main St, Suite 100, Test City, TS, 12345, US'
			);
		} );

		it( 'should handle empty address fields in fallback', () => {
			const emptyAddressDetails = {
				...mockAccountDetails,
				support_address_line2: '',
				support_address_city: '',
				support_address_state: '',
				support_address_postal_code: '',
			};
			const result = formatMerchantAddress( emptyAddressDetails );
			expect( result ).toBe( '123 Main St, US' );
		} );
	} );

	describe( 'formatDeliveryDate', () => {
		it( 'should return placeholder when no date provided', () => {
			const result = formatDeliveryDate( undefined );
			expect( result ).toBe( '<Delivery/Service Date>' );
		} );

		it( 'should format delivery date correctly', () => {
			const result = formatDeliveryDate( '2024-03-20' );
			expect( formatDateTimeFromTimestamp ).toHaveBeenCalled();
			expect( result ).toBe( 'Formatted 1710892800' );
		} );

		it( 'should handle invalid date string', () => {
			const result = formatDeliveryDate( 'invalid-date' );
			expect( formatDateTimeFromTimestamp ).toHaveBeenCalled();
			expect( result ).toBe( 'Formatted NaN' );
		} );
	} );

	describe( 'generateAttachments', () => {
		it( 'should generate attachments for product not received dispute', () => {
			const result = generateAttachments( mockDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Proof of shipping (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
		} );

		it( 'should generate default attachments when no evidence provided', () => {
			const disputeWithoutEvidence = {
				...mockDispute,
				evidence: {},
			};
			const result = generateAttachments( disputeWithoutEvidence );
			expect( result ).toContain(
				'<Attachment description> (Attachment A)'
			);
			expect( result ).toContain(
				'<Attachment description> (Attachment B)'
			);
		} );

		it( 'should handle non-string evidence values', () => {
			const disputeWithNonStringEvidence = {
				...mockDispute,
				evidence: {
					receipt: { url: 'receipt_url' },
					shipping_documentation: { url: 'shipping_url' },
				},
			};
			const result = generateAttachments( disputeWithNonStringEvidence );
			expect( result ).toContain(
				'<Attachment description> (Attachment A)'
			);
			expect( result ).toContain(
				'<Attachment description> (Attachment B)'
			);
		} );

		it( 'should include "Any additional receipts" for duplicate disputes when duplicate_charge_documentation is provided', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					duplicate_charge_documentation:
						'duplicate_charge_documentation_url',
				},
			};
			const result = generateAttachments( duplicateDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Any additional receipts (Attachment B)'
			);
		} );

		it( 'should not include "Any additional receipts" for non-duplicate disputes', () => {
			const nonDuplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					duplicate_charge_documentation:
						'duplicate_charge_documentation_url',
				},
			};
			const result = generateAttachments( nonDuplicateDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).not.toContain( 'Any additional receipts' );
		} );

		it( 'should maintain correct attachment ordering for duplicate disputes', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					duplicate_charge_documentation:
						'duplicate_charge_documentation_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
				},
			};
			const result = generateAttachments( duplicateDispute );
			// Verify the order: Order receipt, Any additional receipts, Customer communication, Refund policy
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Any additional receipts (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
		} );

		it( 'should include "Cancellation logs" for subscription_canceled disputes when cancellation_rebuttal is provided', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_rebuttal: 'cancellation_rebuttal_url',
				},
			};
			const result = generateAttachments( subscriptionCanceledDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation logs (Attachment B)' );
		} );

		it( 'should not include cancellation_rebuttal for disputes other than subscription_canceled or product_not_received', () => {
			const generalDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'general' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_rebuttal: 'cancellation_rebuttal_url',
				},
			};
			const result = generateAttachments( generalDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).not.toContain( 'Cancellation logs' );
			expect( result ).not.toContain( 'Cancellation confirmation' );
		} );

		it( 'should include "Cancellation confirmation" for product_not_received disputes when cancellation_rebuttal is provided', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_rebuttal: 'cancellation_rebuttal_url',
				},
			};
			const result = generateAttachments( productNotReceivedDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Cancellation confirmation (Attachment B)'
			);
			expect( result ).not.toContain( 'Cancellation logs' );
		} );

		it( 'should use "Item condition" label for service_documentation in general disputes', () => {
			const generalDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'general' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
				},
			};
			const result = generateAttachments( generalDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Item condition (Attachment B)' );
			expect( result ).not.toContain(
				'Reservation or booking confirmation'
			);
			expect( result ).not.toContain( 'Event or booking documentation' );
		} );

		it( 'should use "Reservation or booking confirmation" label for service_documentation in product_not_received disputes with booking_reservation product type', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
				},
			};
			const result = generateAttachments(
				productNotReceivedDispute,
				undefined,
				'booking_reservation'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Reservation or booking confirmation (Attachment B)'
			);
			expect( result ).not.toContain( 'Item condition' );
		} );

		it( 'should use "Item condition" label for service_documentation in product_not_received disputes without booking_reservation product type', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
				},
			};
			const result = generateAttachments(
				productNotReceivedDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Item condition (Attachment B)' );
			expect( result ).not.toContain(
				'Reservation or booking confirmation'
			);
		} );

		it( 'should use "Event or booking documentation" as first attachment for product_unacceptable disputes with booking_reservation product type', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'booking_reservation'
			);
			expect( result ).toContain(
				'Event or booking documentation (Attachment A)'
			);
			expect( result ).toContain( 'Order receipt (Attachment B)' );
			expect( result ).not.toContain( 'Item condition' );
		} );

		it( 'should use "Item\'s condition" label for service_documentation in product_unacceptable disputes with physical_product', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( "Item's condition (Attachment B)" );
			expect( result ).not.toContain( 'Event or booking documentation' );
		} );

		it( 'should order all product_unacceptable attachments correctly with full evidence and booking_reservation product type', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					service_documentation: 'service_documentation_url',
					receipt: 'receipt_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'booking_reservation'
			);
			expect( result ).toContain(
				'Event or booking documentation (Attachment A)'
			);
			expect( result ).toContain( 'Order receipt (Attachment B)' );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all product_unacceptable attachments with standard order when physical_product', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					service_documentation: 'service_documentation_url',
					receipt: 'receipt_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'physical_product'
			);
			// With physical_product, order should be standard (receipt first)
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Customer communication (Attachment B)'
			);
			expect( result ).toContain( 'Refund policy (Attachment C)' );
			expect( result ).toContain( "Item's condition (Attachment D)" );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should use "Subscription logs" label for access_activity_log in product_unacceptable disputes (no product-specific override)', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					access_activity_log: 'access_activity_log_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Subscription logs (Attachment B)' );
		} );

		it( 'should order all product_unacceptable attachments correctly with full evidence and physical_product', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					service_documentation: 'service_documentation_url',
					customer_communication: 'customer_communication_url',
					customer_signature: 'customer_signature_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( "Customer's signature (Attachment B)" );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( "Item's condition (Attachment E)" );
			expect( result ).toContain( 'Other documents (Attachment F)' );
		} );

		it( 'should use "Terms of service" label for cancellation_policy in subscription_canceled disputes', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_policy: 'cancellation_policy_url',
				},
			};
			const result = generateAttachments( subscriptionCanceledDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Terms of service (Attachment B)' );
			expect( result ).not.toContain( 'Cancellation policy' );
		} );

		it( 'should use "Cancellation policy" label for cancellation_policy in non-subscription_canceled disputes', () => {
			const generalDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'general' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_policy: 'cancellation_policy_url',
				},
			};
			const result = generateAttachments( generalDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation policy (Attachment B)' );
			expect( result ).not.toContain( 'Terms of service' );
		} );

		it( 'should generate correct attachments for subscription_canceled + booking_reservation scenario', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					customer_communication: 'customer_communication_url',
					cancellation_rebuttal: 'cancellation_rebuttal_url',
					cancellation_policy: 'cancellation_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments( subscriptionCanceledDispute );
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Customer communication (Attachment B)'
			);
			expect( result ).toContain( 'Cancellation logs (Attachment C)' );
			expect( result ).toContain( 'Terms of service (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all product_not_received attachments correctly with full evidence and physical_product product type', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					customer_communication: 'customer_communication_url',
					customer_signature: 'customer_signature_url',
					refund_policy: 'refund_policy_url',
					shipping_documentation: 'shipping_documentation_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productNotReceivedDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Customer communication (Attachment B)'
			);
			expect( result ).toContain( "Customer's signature (Attachment C)" );
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Proof of shipping (Attachment E)' );
			expect( result ).toContain( 'Other documents (Attachment F)' );
		} );

		it( 'should order all fraudulent attachments correctly with full evidence and physical_product product type', () => {
			const fraudulentDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'fraudulent' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					access_activity_log: 'access_activity_log_url',
					customer_communication: 'customer_communication_url',
					customer_signature: 'customer_signature_url',
					refund_policy: 'refund_policy_url',
					shipping_documentation: 'shipping_documentation_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				fraudulentDispute,
				undefined,
				'physical_product'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Prior undisputed transaction history (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( "Customer's signature (Attachment D)" );
			expect( result ).toContain( 'Refund policy (Attachment E)' );
			expect( result ).toContain( 'Proof of shipping (Attachment F)' );
			expect( result ).toContain( 'Other documents (Attachment G)' );
		} );

		it( 'should include "Customer\'s signature" only for physical_product product type', () => {
			const disputeWithSignature: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					customer_signature: 'customer_signature_url',
				},
			};

			// Physical product should include Customer's signature
			const physicalResult = generateAttachments(
				disputeWithSignature,
				undefined,
				'physical_product'
			);
			expect( physicalResult ).toContain( "Customer's signature" );

			// Booking/Reservation should NOT include Customer's signature
			const bookingResult = generateAttachments(
				disputeWithSignature,
				undefined,
				'booking_reservation'
			);
			expect( bookingResult ).not.toContain( "Customer's signature" );

			// Other product types should NOT include Customer's signature
			const otherResult = generateAttachments(
				disputeWithSignature,
				undefined,
				'other'
			);
			expect( otherResult ).not.toContain( "Customer's signature" );

			// No product type specified should NOT include Customer's signature
			const noProductTypeResult = generateAttachments(
				disputeWithSignature,
				undefined,
				undefined
			);
			expect( noProductTypeResult ).not.toContain(
				"Customer's signature"
			);
		} );

		it( 'should label uncategorized_file as "Proof of acceptance" and customer_communication as "Other documents" for credit_not_processed + booking_reservation + refund_was_not_owed', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					uncategorized_file: 'proof_of_acceptance_url',
					refund_policy: 'refund_policy_url',
					customer_communication: 'other_docs_url',
				},
			};

			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'booking_reservation',
				'refund_was_not_owed'
			);
			expect( result ).toContain( 'Proof of acceptance' );
			expect( result ).toContain( 'Other documents' );
			expect( result ).not.toContain( 'Customer communication' );

			// Verify exact ordering: Proof of acceptance (A), Refund policy (B), Other documents (C)
			const proofIndex = result.indexOf( 'Proof of acceptance' );
			const refundPolicyIndex = result.indexOf( 'Refund policy' );
			const otherDocsIndex = result.indexOf( 'Other documents' );
			expect( proofIndex ).toBeLessThan( refundPolicyIndex );
			expect( refundPolicyIndex ).toBeLessThan( otherDocsIndex );
			expect( result ).toContain( 'Proof of acceptance (Attachment A)' );
			expect( result ).toContain( 'Refund policy (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
		} );

		it( 'should label uncategorized_file as "Other documents" for credit_not_processed + booking_reservation + refund_has_been_issued', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					uncategorized_file: 'some_file_url',
				},
			};

			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'booking_reservation',
				'refund_has_been_issued'
			);
			expect( result ).toContain( 'Other documents' );
			expect( result ).not.toContain( 'Proof of acceptance' );
		} );

		it( 'should order all credit_not_processed Scenario A attachments correctly with booking_reservation product type', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					receipt: 'refund_receipt_url',
					cancellation_rebuttal: 'cancellation_logs_url',
					customer_communication: 'other_docs_url',
				},
			};
			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'booking_reservation',
				'refund_has_been_issued'
			);
			// Verify exact ordering: Refund receipt (A), Cancellation logs (B), Other documents (C)
			expect( result ).toContain( 'Refund receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation logs (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
			expect( result ).not.toContain( 'Order receipt' );
			expect( result ).not.toContain( 'Customer communication' );
		} );

		// Digital Product/Service cover letter ordering tests

		it( 'should order all fraudulent attachments correctly for digital_product_or_service', () => {
			const fraudulentDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'fraudulent' as DisputeReason,
				evidence: {
					access_activity_log: 'access_activity_log_url',
					service_documentation: 'service_documentation_url',
					customer_communication: 'customer_communication_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				fraudulentDispute,
				undefined,
				'digital_product_or_service'
			);
			expect( result ).toContain(
				'Login or usage records (Attachment A)'
			);
			expect( result ).toContain(
				'Prior undisputed transaction history (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Other documents (Attachment D)' );
			expect( result ).not.toContain( "Customer's signature" );
			expect( result ).not.toContain( 'Order receipt' );
			expect( result ).not.toContain( 'Refund policy' );
		} );

		it( 'should order all product_not_received attachments correctly for digital_product_or_service', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					access_activity_log: 'access_activity_log_url',
					customer_communication: 'customer_communication_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productNotReceivedDispute,
				undefined,
				'digital_product_or_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Login or usage records (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Other documents (Attachment D)' );
			expect( result ).not.toContain( 'Refund policy' );
		} );

		it( 'should order all product_unacceptable attachments correctly for digital_product_or_service', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					service_documentation: 'service_documentation_url',
					receipt: 'receipt_url',
					access_activity_log: 'access_activity_log_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'digital_product_or_service'
			);
			expect( result ).toContain(
				'Proof of delivered service (Attachment A)'
			);
			expect( result ).toContain( 'Order receipt (Attachment B)' );
			expect( result ).toContain(
				'Login or usage records (Attachment C)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment D)'
			);
			expect( result ).toContain( 'Refund policy (Attachment E)' );
			expect( result ).toContain( 'Other documents (Attachment F)' );
		} );

		it( 'should order all subscription_canceled attachments correctly for digital_product_or_service', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					cancellation_rebuttal: 'cancellation_rebuttal_url',
					customer_communication: 'customer_communication_url',
					access_activity_log: 'access_activity_log_url',
					cancellation_policy: 'cancellation_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				subscriptionCanceledDispute,
				undefined,
				'digital_product_or_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation logs (Attachment B)' );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain(
				'Login or usage records (Attachment D)'
			);
			expect( result ).toContain( 'Terms of service (Attachment E)' );
			expect( result ).toContain( 'Other documents (Attachment F)' );
		} );

		it( 'should order all credit_not_processed Scenario A attachments correctly for digital_product_or_service', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					receipt: 'refund_receipt_url',
					cancellation_rebuttal: 'cancellation_logs_url',
					customer_communication: 'other_docs_url',
				},
			};
			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'digital_product_or_service',
				'refund_has_been_issued'
			);
			expect( result ).toContain( 'Refund receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation logs (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
			expect( result ).not.toContain( 'Order receipt' );
			expect( result ).not.toContain( 'Customer communication' );
		} );

		it( 'should order all credit_not_processed Scenario B attachments correctly for digital_product_or_service', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					uncategorized_file: 'proof_of_acceptance_url',
					refund_policy: 'refund_policy_url',
					customer_communication: 'other_docs_url',
				},
			};
			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'digital_product_or_service',
				'refund_was_not_owed'
			);
			expect( result ).toContain( 'Proof of acceptance (Attachment A)' );
			expect( result ).toContain( 'Refund policy (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
			expect( result ).not.toContain( 'Customer communication' );
		} );

		it( 'should order all duplicate is_duplicate attachments correctly for digital_product_or_service', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					duplicate_charge_documentation:
						'duplicate_charge_documentation_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				duplicateDispute,
				'is_duplicate',
				'digital_product_or_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Refund receipt (Attachment B)' );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all duplicate is_not_duplicate attachments correctly for digital_product_or_service', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'receipt_url',
					duplicate_charge_documentation:
						'duplicate_charge_documentation_url',
					customer_communication: 'customer_communication_url',
					refund_policy: 'refund_policy_url',
					uncategorized_file: 'uncategorized_file_url',
				},
			};
			const result = generateAttachments(
				duplicateDispute,
				'is_not_duplicate',
				'digital_product_or_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Any additional receipts (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		// Offline Service cover letter ordering tests

		it( 'should order all fraudulent attachments correctly for offline_service', () => {
			const fraudulentDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'fraudulent' as DisputeReason,
				evidence: {
					access_activity_log: 'url',
					customer_communication: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				fraudulentDispute,
				undefined,
				'offline_service'
			);
			expect( result ).toContain(
				'Prior undisputed transaction history (Attachment A)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment B)'
			);
			expect( result ).toContain( 'Other documents (Attachment C)' );
		} );

		it( 'should order all product_not_received attachments correctly for offline_service', () => {
			const productNotReceivedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_not_received' as DisputeReason,
				evidence: {
					receipt: 'url',
					service_documentation: 'url',
					customer_communication: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				productNotReceivedDispute,
				undefined,
				'offline_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Proof of service completion (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Other documents (Attachment D)' );
		} );

		it( 'should order all product_unacceptable attachments correctly for offline_service', () => {
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
				evidence: {
					service_documentation: 'url',
					receipt: 'url',
					customer_communication: 'url',
					refund_policy: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				productUnacceptableDispute,
				undefined,
				'offline_service'
			);
			expect( result ).toContain(
				'Proof of delivered service (Attachment A)'
			);
			expect( result ).toContain( 'Order receipt (Attachment B)' );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all subscription_canceled attachments correctly for offline_service', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
				evidence: {
					receipt: 'url',
					customer_communication: 'url',
					cancellation_rebuttal: 'url',
					cancellation_policy: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				subscriptionCanceledDispute,
				undefined,
				'offline_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Customer communication (Attachment B)'
			);
			expect( result ).toContain( 'Cancellation logs (Attachment C)' );
			expect( result ).toContain( 'Terms of service (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all credit_not_processed Scenario A attachments correctly for offline_service', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					receipt: 'url',
					cancellation_rebuttal: 'url',
					customer_communication: 'url',
				},
			};
			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'offline_service',
				'refund_has_been_issued'
			);
			expect( result ).toContain( 'Refund receipt (Attachment A)' );
			expect( result ).toContain( 'Cancellation logs (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
			expect( result ).not.toContain( 'Order receipt' );
			expect( result ).not.toContain( 'Customer communication' );
		} );

		it( 'should order all credit_not_processed Scenario B attachments correctly for offline_service', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
				evidence: {
					uncategorized_file: 'url',
					refund_policy: 'url',
					customer_communication: 'url',
				},
			};
			const result = generateAttachments(
				creditNotProcessedDispute,
				undefined,
				'offline_service',
				'refund_was_not_owed'
			);
			expect( result ).toContain( 'Proof of acceptance (Attachment A)' );
			expect( result ).toContain( 'Refund policy (Attachment B)' );
			expect( result ).toContain( 'Other documents (Attachment C)' );
			expect( result ).not.toContain( 'Customer communication' );
		} );

		it( 'should order all duplicate is_duplicate attachments correctly for offline_service', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'url',
					duplicate_charge_documentation: 'url',
					customer_communication: 'url',
					refund_policy: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				duplicateDispute,
				'is_duplicate',
				'offline_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain( 'Refund receipt (Attachment B)' );
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );

		it( 'should order all duplicate is_not_duplicate attachments correctly for offline_service', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
				evidence: {
					receipt: 'url',
					duplicate_charge_documentation: 'url',
					customer_communication: 'url',
					refund_policy: 'url',
					uncategorized_file: 'url',
				},
			};
			const result = generateAttachments(
				duplicateDispute,
				'is_not_duplicate',
				'offline_service'
			);
			expect( result ).toContain( 'Order receipt (Attachment A)' );
			expect( result ).toContain(
				'Any additional receipts (Attachment B)'
			);
			expect( result ).toContain(
				'Customer communication (Attachment C)'
			);
			expect( result ).toContain( 'Refund policy (Attachment D)' );
			expect( result ).toContain( 'Other documents (Attachment E)' );
		} );
	} );

	describe( 'generateHeader', () => {
		it( 'should generate header with merchant details', () => {
			const result = generateHeader( mockCoverLetterData );
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( '123 Main St' );
			expect( result ).toContain( 'test@example.com' );
			expect( result ).toContain( '123-456-7890' );
		} );

		it( 'should handle missing merchant details', () => {
			const dataWithMissingDetails = {
				...mockCoverLetterData,
				merchantEmail: '',
				merchantPhone: '',
			};
			const result = generateHeader( dataWithMissingDetails );
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( '123 Main St' );
			expect( result ).not.toContain( 'test@example.com' );
			expect( result ).not.toContain( '123-456-7890' );
		} );
	} );

	describe( 'generateRecipient', () => {
		it( 'should generate recipient section', () => {
			const result = generateRecipient( mockCoverLetterData );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
		} );

		it( 'should handle missing bank name', () => {
			const dataWithoutBank = {
				...mockCoverLetterData,
				acquiringBank: '<Bank Name>',
			};
			const result = generateRecipient( dataWithoutBank );
			expect( result ).toContain( '<Bank Name>' );
		} );
	} );

	describe( 'generateBody', () => {
		it( 'should generate body for product not received dispute', () => {
			const attachmentsList = '• Test Attachment';
			const result = generateBody(
				mockCoverLetterData,
				mockDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
		} );

		it( 'should generate body for product unacceptable dispute', () => {
			const attachmentsList = '• Test Attachment';
			const productUnacceptableDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'product_unacceptable' as DisputeReason,
			};
			const result = generateBody(
				mockCoverLetterData,
				productUnacceptableDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
			expect( result ).toContain(
				'The product matched the description provided at the time of sale'
			);
		} );

		it( 'should generate body for credit not processed dispute with refund issued', () => {
			const attachmentsList = '• Test Attachment';
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const coverLetterDataWithRefundStatus = {
				...mockCoverLetterData,
				refundStatus: 'refund_has_been_issued',
			};
			const result = generateBody(
				coverLetterDataWithRefundStatus,
				creditNotProcessedDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'was refunded on' );
			expect( result ).toContain( '10.00 USD' );
			expect( result ).toContain(
				"The refund was processed through our payment provider and should be visible on the customer's statement within 7 - 10 business days."
			);
		} );

		it( 'should generate body for credit not processed dispute with refund not owed', () => {
			const attachmentsList = '• Test Attachment';
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const coverLetterDataWithRefundStatus = {
				...mockCoverLetterData,
				refundStatus: 'refund_was_not_owed',
			};
			const result = generateBody(
				coverLetterDataWithRefundStatus,
				creditNotProcessedDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'The customer requested a refund outside of the eligible window outlined in our refund policy, which was clearly presented on the website and on the order confirmation.'
			);
		} );

		it( 'should generate body for credit not processed dispute with missing refund status', () => {
			const attachmentsList = '• Test Attachment';
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const coverLetterDataWithoutRefundStatus = {
				...mockCoverLetterData,
				refundStatus: undefined,
			};
			const result = generateBody(
				coverLetterDataWithoutRefundStatus,
				creditNotProcessedDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'The customer requested a refund outside of the eligible window outlined in our refund policy, which was clearly presented on the website and on the order confirmation.'
			);
		} );

		it( 'should generate complete cover letter for general dispute', () => {
			const generalDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'general' as DisputeReason,
			};
			const result = generateCoverLetter(
				generalDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain(
				'123 Main St, Suite 100, Test City, TS, 12345, US'
			);
			expect( result ).toContain( 'test@example.com' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
			expect( result ).toContain( 'ordered Test Product on' );
			expect( result ).toContain( 'and received it on' );
			expect( result ).toContain( 'Dear Dispute Resolution Team,' );
			expect( result ).toContain( 'Thank you,' );
		} );

		it( 'should generate body for non-product-not-received dispute', () => {
			const disputeWithDifferentReason = {
				...mockDispute,
				reason: 'fraudulent' as const,
			};
			const attachmentsList = '• Test Attachment';
			const result = generateBody(
				mockCoverLetterData,
				disputeWithDifferentReason,
				attachmentsList
			);
			expect( result ).toContain( 'legitimate cardholder' );
			expect( result ).not.toContain( 'received it on' );
		} );

		it( 'should handle missing customer name', () => {
			const mockCoverLetterDataWithoutCustomer = {
				...mockCoverLetterData,
				customerName: '<Customer Name>',
			};
			const attachmentsList = '• Test Attachment';
			const result = generateBody(
				mockCoverLetterDataWithoutCustomer,
				mockDispute,
				attachmentsList
			);
			expect( result ).toContain( '<Customer Name>' );
		} );

		it( 'should generate body for subscription canceled dispute', () => {
			const attachmentsList = '• Test Attachment';
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
			};
			const result = generateBody(
				mockCoverLetterData,
				subscriptionCanceledDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
			expect( result ).toContain( 'subscribed to' );
			expect( result ).toContain(
				"and was billed according to the terms accepted at the time of signup. The customer's account remained active and no cancellation was recorded prior to the billing date."
			);
		} );

		it( 'should generate body for duplicate dispute with is_duplicate status', () => {
			const attachmentsList = '• Test Attachment';
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const coverLetterDataWithDuplicateStatus = {
				...mockCoverLetterData,
				duplicateStatus: 'is_duplicate',
			};
			const result = generateBody(
				coverLetterDataWithDuplicateStatus,
				duplicateDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'was a duplicate of a previous transaction'
			);
			expect( result ).toContain(
				'A refund has already been issued to the customer on'
			);
			expect( result ).toContain( '10.00 USD' );
			expect( result ).toContain(
				"This refund should be visible on the customer's statement within 7 - 10 business days."
			);
		} );

		it( 'should generate body for duplicate dispute with is_not_duplicate status', () => {
			const attachmentsList = '• Test Attachment';
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const coverLetterDataWithDuplicateStatus = {
				...mockCoverLetterData,
				duplicateStatus: 'is_not_duplicate',
			};
			const result = generateBody(
				coverLetterDataWithDuplicateStatus,
				duplicateDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'placed two distinct orders' );
			expect( result ).toContain(
				'Both transactions were legitimate, fulfilled independently, and are not duplicates'
			);
		} );

		it( 'should generate body for duplicate dispute with missing duplicate status', () => {
			const attachmentsList = '• Test Attachment';
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const coverLetterDataWithoutDuplicateStatus = {
				...mockCoverLetterData,
				duplicateStatus: undefined,
			};
			const result = generateBody(
				coverLetterDataWithoutDuplicateStatus,
				duplicateDispute,
				attachmentsList
			);
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'placed two distinct orders' );
			expect( result ).toContain(
				'Both transactions were legitimate, fulfilled independently, and are not duplicates'
			);
		} );
	} );

	describe( 'generateClosing', () => {
		it( 'should generate closing section', () => {
			const result = generateClosing( mockCoverLetterData );
			expect( result ).toContain( 'Thank you' );
			expect( result ).toContain( 'Test Store' );
		} );

		it( 'should handle missing merchant name', () => {
			const dataWithoutMerchant = {
				...mockCoverLetterData,
				merchantName: '',
			};
			const result = generateClosing( dataWithoutMerchant );
			expect( result ).toContain( 'Thank you' );
			expect( result ).not.toContain( 'Test Store' );
		} );
	} );

	describe( 'generateCoverLetter', () => {
		it( 'should generate complete cover letter', () => {
			const result = generateCoverLetter(
				mockDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
		} );

		it( 'should use fallback values when data is missing', () => {
			const disputeWithMissingData = {
				...mockDispute,
				id: 'dp_123', // Keep required fields
				charge: {
					...mockDispute.charge,
					id: 'ch_123', // Keep required fields
				},
			};
			const result = generateCoverLetter(
				disputeWithMissingData,
				mockAccountDetails,
				{},
				null
			);
			expect( result ).toContain( '<Bank Name>' );
		} );

		it( 'should handle missing product description', () => {
			const disputeWithoutProduct = {
				...mockDispute,
				evidence: {
					...mockDispute.evidence,
					product_description: '',
				},
				charge: {
					...mockDispute.charge,
					level3: {
						line_items: [],
					},
				},
			};
			const result = generateCoverLetter(
				disputeWithoutProduct,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( '<Product>' );
		} );

		it( 'should handle missing transaction date', () => {
			const disputeWithoutDate = {
				...mockDispute,
				created: 0, // Use 0 instead of undefined to satisfy TypeScript
			};
			const result = generateCoverLetter(
				disputeWithoutDate,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( '<Transaction Date>' );
		} );

		it( 'should generate cover letter for credit not processed dispute with refund issued', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const result = generateCoverLetter(
				creditNotProcessedDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank',
				'refund_has_been_issued'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'was refunded on' );
			expect( result ).toContain( '10.00 USD' );
		} );

		it( 'should generate cover letter for credit not processed dispute with refund not owed', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const result = generateCoverLetter(
				creditNotProcessedDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank',
				'refund_was_not_owed'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'The customer requested a refund outside of the eligible window outlined in our refund policy, which was clearly presented on the website and on the order confirmation.'
			);
		} );

		it( 'should handle credit not processed dispute with missing refund status', () => {
			const creditNotProcessedDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'credit_not_processed' as DisputeReason,
			};
			const result = generateCoverLetter(
				creditNotProcessedDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'The customer requested a refund outside of the eligible window outlined in our refund policy, which was clearly presented on the website and on the order confirmation.'
			);
		} );

		it( 'should generate cover letter for subscription canceled dispute', () => {
			const subscriptionCanceledDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'subscription_canceled' as DisputeReason,
			};
			const result = generateCoverLetter(
				subscriptionCanceledDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'John Doe' );
			expect( result ).toContain( 'Test Product' );
			expect( result ).toContain( 'subscribed to' );
			expect( result ).toContain(
				"and was billed according to the terms accepted at the time of signup. The customer's account remained active and no cancellation was recorded prior to the billing date."
			);
		} );

		it( 'should generate cover letter for duplicate dispute with is_duplicate status', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const result = generateCoverLetter(
				duplicateDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank',
				undefined,
				'is_duplicate'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain(
				'was a duplicate of a previous transaction'
			);
			expect( result ).toContain(
				'A refund has already been issued to the customer on'
			);
			expect( result ).toContain( '10.00 USD' );
		} );

		it( 'should generate cover letter for duplicate dispute with is_not_duplicate status', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const result = generateCoverLetter(
				duplicateDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank',
				undefined,
				'is_not_duplicate'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'placed two distinct orders' );
			expect( result ).toContain(
				'Both transactions were legitimate, fulfilled independently, and are not duplicates'
			);
		} );

		it( 'should handle duplicate dispute with missing duplicate status', () => {
			const duplicateDispute: ExtendedDispute = {
				...mockDispute,
				reason: 'duplicate' as DisputeReason,
			};
			const result = generateCoverLetter(
				duplicateDispute,
				mockAccountDetails,
				mockSettings,
				'Test Bank'
			);
			expect( result ).toContain( 'Test Store' );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
			expect( result ).toContain( 'ch_123' );
			expect( result ).toContain( 'placed two distinct orders' );
			expect( result ).toContain(
				'Both transactions were legitimate, fulfilled independently, and are not duplicates'
			);
		} );
	} );
} );
