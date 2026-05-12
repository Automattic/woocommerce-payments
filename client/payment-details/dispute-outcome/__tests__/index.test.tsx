/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeOutcomeView from '../index';
import { getExpectedFieldStatus } from 'wcpay/disputes/new-evidence/evidence-field-status';
import type { ChargeDispute } from 'wcpay/types/charges';

const buildDispute = (
	overrides: Partial< ChargeDispute > = {}
): ChargeDispute =>
	( {
		id: 'dp_test',
		amount: 2000,
		currency: 'usd',
		charge: 'ch_test',
		order: null,
		balance_transactions: [],
		created: 1693453017,
		evidence: {
			shipping_documentation: 'tracked',
			shipping_address: '123 Main St',
		},
		evidence_details: {
			due_by: 0,
			has_evidence: false,
			past_due: false,
			submission_count: 0,
		},
		issuer_evidence: null,
		metadata: {},
		payment_intent: 'pi_test',
		reason: 'product_unacceptable',
		status: 'lost',
		...overrides,
	} as ChargeDispute );

describe( 'DisputeOutcomeView', () => {
	it( 'renders the "Evidence Submitted" section heading', () => {
		render( <DisputeOutcomeView dispute={ buildDispute() } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
	} );

	it( 'renders a list item per field returned by the helper', () => {
		const dispute = buildDispute( {
			metadata: { __product_type: 'physical_product' },
		} );
		const expected = getExpectedFieldStatus(
			dispute.reason,
			'physical_product',
			dispute.evidence
		);

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect( screen.getAllByRole( 'listitem' ) ).toHaveLength(
			expected.length
		);
		// Physical-product-only field; presence proves the resolved type reached the helper.
		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'prefers metadata.__product_type over order.suggested_product_type', () => {
		const dispute = buildDispute( {
			metadata: { __product_type: 'physical_product' },
			order: {
				id: 1,
				number: '1',
				url: '',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
				suggested_product_type: 'digital_product_or_service',
			},
		} );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		// Physical-product-only field; would be absent if the digital fallback had won.
		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'falls back to order.suggested_product_type when metadata is empty', () => {
		const dispute = buildDispute( {
			metadata: {},
			order: {
				id: 1,
				number: '1',
				url: '',
				customer_url: null,
				customer_email: null,
				customer_name: null,
				ip_address: '127.0.0.1',
				suggested_product_type: 'physical_product',
			},
		} );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect(
			screen.getByText( /Shipping documentation/i )
		).toBeInTheDocument();
	} );

	it( 'renders only the heading when no product type is available', () => {
		const dispute = buildDispute( { metadata: {}, order: null } );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
		expect( screen.queryAllByRole( 'listitem' ) ).toHaveLength( 0 );
	} );

	describe( 'optional-missing collapse by status', () => {
		// `fraudulent × physical_product` with rich evidence yields a row
		// shape that includes optional_missing entries (receipt,
		// refund_policy) alongside provided rows. Mirrors the
		// `wonFraudulentPhysical` fixture without rebuilding it.
		const collapsibleDispute = (
			status: 'won' | 'lost' | 'warning_closed'
		) =>
			buildDispute( {
				status,
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: {
					customer_communication: 'present',
					shipping_documentation: 'present',
					shipping_address: 'present',
					shipping_tracking_number: 'present',
					shipping_date: '2026-04-15',
					customer_signature: 'present',
				},
			} );

		it( 'collapses optional_missing rows when status is won', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'won' ) } />
			);

			const disclosure = screen.queryByRole( 'group' );
			expect( disclosure ).not.toBeNull();
			expect( disclosure?.tagName ).toBe( 'DETAILS' );
		} );

		it( 'collapses optional_missing rows when status is warning_closed', () => {
			render(
				<DisputeOutcomeView
					dispute={ collapsibleDispute( 'warning_closed' ) }
				/>
			);

			expect( screen.queryByRole( 'group' ) ).not.toBeNull();
		} );

		it( 'leaves optional_missing rows inline when status is lost', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'lost' ) } />
			);

			expect( screen.queryByRole( 'group' ) ).toBeNull();
		} );
	} );
} );
