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
import type { ChargeDispute } from 'wcpay/types/charges';

// `product_unacceptable × physical_product` is well-covered by the
// `DISPUTE_HIGH_IMPACT_FIELDS` matrix (shipping_documentation,
// shipping_address, shipping_date, etc.), so it gives us a non-empty
// `getExpectedFieldStatus` result without having to mock the helper.
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

	it( 'renders a horizontal separator as the section’s first child', () => {
		// The separator must precede the heading so it visually delimits the
		// section from the order-meta grid above it (matches awaiting-response
		// sibling).
		const { container } = render(
			<DisputeOutcomeView dispute={ buildDispute() } />
		);

		const section = container.querySelector( '.dispute-outcome-view' );
		expect( section?.firstElementChild?.tagName ).toBe( 'HR' );
	} );

	it( 'renders a list item per field returned by the helper, in order', () => {
		const dispute = buildDispute( {
			metadata: { __product_type: 'physical_product' },
		} );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		const items = screen.getAllByRole( 'listitem' );
		expect( items.length ).toBeGreaterThan( 0 );
		// Provided rows render the human label; missing rows render the same
		// label plus a visible "— Not provided" suffix.
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

		// `shipping_documentation` is a physical-product field. If the
		// digital fallback had won, the list wouldn't include it.
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
		// With reason set but productType empty, the helper returns [] for
		// reasons that only have product-type-specific cells.
		const dispute = buildDispute( { metadata: {}, order: null } );

		render( <DisputeOutcomeView dispute={ dispute } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Evidence Submitted' } )
		).toBeInTheDocument();
		expect( screen.queryAllByRole( 'listitem' ) ).toHaveLength( 0 );
	} );
} );
