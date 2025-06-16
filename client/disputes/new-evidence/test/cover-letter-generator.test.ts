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
		it( 'should format merchant address correctly', () => {
			const result = formatMerchantAddress( mockAccountDetails );
			expect( result ).toBe(
				'123 Main St, Suite 100, Test City, TS 12345 US'
			);
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
	} );

	describe( 'generateAttachments', () => {
		it( 'should generate attachments for product not received dispute', () => {
			const result = generateAttachments( mockDispute );
			expect( result ).toContain( 'Proof of Purchase' );
			expect( result ).toContain( 'Proof of Shipping' );
			expect( result ).toContain( 'Proof of Delivery' );
		} );

		it( 'should generate default attachments when no evidence provided', () => {
			const disputeWithoutEvidence = {
				...mockDispute,
				evidence: {},
			};
			const result = generateAttachments( disputeWithoutEvidence );
			expect( result ).toContain( 'Proof of Purchase' );
			expect( result ).toContain( 'Proof of Shipping' );
			expect( result ).toContain( 'Proof of Delivery' );
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
	} );

	describe( 'generateRecipient', () => {
		it( 'should generate recipient section', () => {
			const result = generateRecipient( mockCoverLetterData );
			expect( result ).toContain( 'Test Bank' );
			expect( result ).toContain( 'dp_123' );
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
	} );

	describe( 'generateClosing', () => {
		it( 'should generate closing section', () => {
			const result = generateClosing( mockCoverLetterData );
			expect( result ).toContain( 'Thank you' );
			expect( result ).toContain( 'Test Store' );
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
	} );
} );
