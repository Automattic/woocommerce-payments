/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import RecommendationsList from '../recommendations-list';
import type { EvidenceFieldStatus } from 'wcpay/disputes/new-evidence/types';

const couldHelpFields: EvidenceFieldStatus[] = [
	{
		key: 'shipping_address',
		label: 'Shipping address',
		state: 'expected_missing',
	},
	{
		key: 'shipping_tracking_number',
		label: 'Tracking number',
		state: 'expected_missing',
	},
];

const keepDoingFields: EvidenceFieldStatus[] = [
	{
		key: 'shipping_address',
		label: 'Shipping address',
		state: 'provided',
	},
];

describe( 'RecommendationsList', () => {
	it( 'renders the "What could help" heading for the could_help outcome', () => {
		render(
			<RecommendationsList
				fields={ couldHelpFields }
				outcome="could_help"
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: /what could help/i } )
		).toBeInTheDocument();
	} );

	it( 'renders the "What to keep doing" heading for the keep_doing outcome', () => {
		render(
			<RecommendationsList
				fields={ keepDoingFields }
				outcome="keep_doing"
			/>
		);

		expect(
			screen.getByRole( 'heading', { name: /what to keep doing/i } )
		).toBeInTheDocument();
	} );

	it( 'renders one list item per field with its label', () => {
		render(
			<RecommendationsList
				fields={ couldHelpFields }
				outcome="could_help"
			/>
		);

		const items = screen.getAllByRole( 'listitem' );
		expect( items ).toHaveLength( 2 );
		expect( screen.getByText( 'Shipping address' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Tracking number' ) ).toBeInTheDocument();
	} );

	it( 'returns null for empty fields', () => {
		const { container } = render(
			<RecommendationsList fields={ [] } outcome="could_help" />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'includes TODO(riskops) placeholder markers in the rendered output', () => {
		// Defense against accidental removal of the TODO marker before
		// the final RiskOps copy lands. The marker is the canary; when
		// the real strings ship, the placeholder string disappears and
		// this test updates with it.
		render(
			<RecommendationsList
				fields={ couldHelpFields }
				outcome="could_help"
			/>
		);

		const placeholders = screen.getAllByText( /TODO\(riskops\)/ );
		expect( placeholders.length ).toBeGreaterThan( 0 );
	} );
} );
