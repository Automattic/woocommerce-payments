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
		// Default to warning_closed so existing tests focus on the
		// Evidence Submitted section without the recommendations list
		// also rendering. Won/lost behavior has its own dedicated tests.
		status: 'warning_closed',
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

	describe( 'recommendations section', () => {
		it( 'renders "What could help" for a lost dispute', () => {
			const dispute = buildDispute( {
				status: 'lost',
				metadata: { __product_type: 'physical_product' },
			} );

			render( <DisputeOutcomeView dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', { name: /what could help/i } )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /what to keep doing/i,
				} )
			).not.toBeInTheDocument();
		} );

		it( 'renders "What to keep doing" for a won dispute when a high-impact field is provided', () => {
			const dispute = buildDispute( {
				status: 'won',
				metadata: { __product_type: 'physical_product' },
				// shipping_address + shipping_documentation are high-impact
				// for product_unacceptable + physical_product? No — for
				// product_unacceptable the high-impact set differs. Pin
				// reason to product_not_received where shipping_address is
				// high-impact to keep this test against the actual map.
				reason: 'product_not_received',
				evidence: {
					shipping_address: '123 Main St',
					shipping_tracking_number: '1Z999',
				},
			} );

			render( <DisputeOutcomeView dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', { name: /what to keep doing/i } )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', { name: /what could help/i } )
			).not.toBeInTheDocument();
		} );

		it( 'renders neither recommendations heading for a warning_closed dispute', () => {
			const dispute = buildDispute( {
				status: 'warning_closed',
				metadata: { __product_type: 'physical_product' },
			} );

			render( <DisputeOutcomeView dispute={ dispute } /> );

			// Evidence Submitted still renders for warning_closed.
			expect(
				screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', { name: /what could help/i } )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /what to keep doing/i,
				} )
			).not.toBeInTheDocument();
		} );
	} );
} );
