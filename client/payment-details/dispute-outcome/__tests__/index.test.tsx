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
		// Set a real product type so the helper produces matrix-derived
		// rows alongside the universal cover letter row, mirroring the
		// usual production shape.
		render(
			<DisputeOutcomeView
				dispute={ buildDispute( {
					metadata: { __product_type: 'physical_product' },
				} ) }
			/>
		);

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

	it( 'renders only the cover letter row when no product type is available', () => {
		// With neither `metadata.__product_type` nor
		// `order.suggested_product_type`, the helper still emits the
		// universal cover letter row (Catherine's request: it's
		// merchant-actionable on every dispute), so the section renders
		// with that single row only.
		render(
			<DisputeOutcomeView
				dispute={ buildDispute( { metadata: {}, order: null } ) }
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
		const items = screen.getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ] ).toHaveTextContent( /Cover letter/ );
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

		// Locate the disclosure via its visible summary copy + walk up
		// to <details>, rather than relying on the implicit role
		// mapping; mirrors what a user can see on the page.
		const findDisclosure = () => {
			const summary = screen.queryByText( /optional evidence field/i );
			return summary?.closest( 'details' ) ?? null;
		};

		it( 'collapses optional_missing rows when status is won', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'won' ) } />
			);

			expect( findDisclosure() ).not.toBeNull();
		} );

		it( 'collapses optional_missing rows when status is warning_closed', () => {
			render(
				<DisputeOutcomeView
					dispute={ collapsibleDispute( 'warning_closed' ) }
				/>
			);

			expect( findDisclosure() ).not.toBeNull();
		} );

		it( 'leaves optional_missing rows inline when status is lost', () => {
			render(
				<DisputeOutcomeView dispute={ collapsibleDispute( 'lost' ) } />
			);

			expect( findDisclosure() ).toBeNull();
		} );
	} );
} );
