/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';

/**
 * Internal dependencies
 */
import CoverLetter from '../cover-letter';
import PAYMENT_METHOD_IDS from 'wcpay/constants/payment-method';
import type {
	DisputeStatus,
	EvidenceDetails,
	DisputeReason,
} from 'wcpay/types/disputes';

describe( 'CoverLetter', () => {
	const baseProps = {
		value: 'Cover letter text',
		onChange: jest.fn(),
		dispute: {
			status: 'needs_response' as DisputeStatus,
			id: 'dp_123',
			evidence_details: {
				has_evidence: false,
				due_by: Date.now() / 1000,
				past_due: false,
				submission_count: 0,
			} as EvidenceDetails,
			metadata: {},
			order: {
				id: 123,
				number: '123',
				url: 'https://example.com/order/123',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
			} as OrderDetails,
			evidence: {},
			issuer_evidence: [],
			charge: {
				id: 'ch_123',
				amount: 1000,
				amount_captured: 1000,
				amount_refunded: 0,
				application: null,
				application_fee: null,
				application_fee_amount: 0,
				balance_transaction: {
					id: 'txn_123',
					amount: 1000,
					currency: 'usd',
					created: Date.now() / 1000,
					description: null,
					fee: 0,
					fee_details: [],
					net: 1000,
					reporting_category: 'charge',
					source: 'ch_123',
					status: 'available',
					type: 'charge',
				},
				billing_details: {
					email: 'test@example.com',
					name: 'Test Customer',
					phone: '+1234567890',
					address: {
						city: 'Test City',
						country: 'US',
						line1: '123 Test St',
						line2: null,
						postal_code: '12345',
						state: 'CA',
					},
				},
				calculated_statement_descriptor: null,
				captured: true,
				created: Date.now() / 1000,
				currency: 'usd',
				customer: null,
				description: null,
				dispute: null,
				failure_code: null,
				failure_message: null,
				payment_method_details: {
					type: PAYMENT_METHOD_IDS.CARD,
					card: { network: 'visa' },
				} as const,
				disputed: false,
				order: null,
				outcome: null,
				paid: true,
				payment_intent: 'pi_123',
				payment_method: 'pm_123',
				receipt_email: null,
				receipt_number: null,
				receipt_url: null,
				refunded: false,
				refunds: { data: [] },
				review: null,
				shipping: null,
				source: null,
				source_transfer: null,
				statement_descriptor: null,
				statement_descriptor_suffix: null,
				status: 'succeeded',
				transfer: null,
				transfer_data: null,
				transfer_group: null,
				paydown: null,
			},
			amount: 1000,
			currency: 'usd',
			created: Date.now() / 1000,
			balance_transactions: [],
			reason: 'general' as DisputeReason,
			payment_intent: 'pi_123',
		},
		bankName: 'Bank Name',
	};

	it( 'renders textarea and print button', () => {
		render( <CoverLetter { ...baseProps } /> );
		expect( screen.getByLabelText( /COVER LETTER/i ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /View cover letter/i } )
		).toBeInTheDocument();
	} );

	it( 'calls onChange when textarea changes', () => {
		render( <CoverLetter { ...baseProps } /> );
		const textarea = screen.getByRole( 'textbox' );
		fireEvent.change( textarea, {
			target: { value: 'New text' },
		} );
		expect( baseProps.onChange ).toHaveBeenCalledWith( 'New text' );
	} );

	it( 'renders textarea with value', () => {
		render( <CoverLetter { ...baseProps } /> );
		const textarea = screen.getByLabelText( /COVER LETTER/i );
		expect( textarea ).toHaveValue( 'Cover letter text' );
	} );
} );
