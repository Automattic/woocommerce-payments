/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import DisputeRecommendationsCard from '../index';
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
		evidence: {},
		evidence_details: {
			due_by: 0,
			has_evidence: false,
			past_due: false,
			submission_count: 0,
		},
		issuer_evidence: null,
		metadata: {},
		payment_intent: 'pi_test',
		reason: 'product_not_received',
		status: 'lost',
		...overrides,
	} as ChargeDispute );

describe( 'DisputeRecommendationsCard', () => {
	it( 'renders the "What could help in future disputes" heading for a lost dispute when recommendations fire', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: {}, // shipping_tracking_number absent → critical recommendation fires
		} );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		expect(
			screen.getByRole( 'heading', {
				name: /what could help in future disputes/i,
			} )
		).toBeInTheDocument();
	} );

	it( 'renders the "What to keep doing" heading for a won dispute when recommendations fire', () => {
		const dispute = buildDispute( {
			status: 'won',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: {
				shipping_tracking_number: '1Z999',
				shipping_carrier: 'UPS',
			},
		} );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		expect(
			screen.getByRole( 'heading', { name: /what to keep doing/i } )
		).toBeInTheDocument();
	} );

	it( 'returns null for a warning_closed dispute', () => {
		const dispute = buildDispute( {
			status: 'warning_closed',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
		} );

		const { container } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'returns null when status maps to an outcome but no recommendations fire', () => {
		// Won dispute on a reason × productType the catalog has no entry for.
		const dispute = buildDispute( {
			status: 'won',
			reason: 'bank_cannot_process',
			metadata: { __product_type: 'physical_product' },
		} );

		const { container } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders each matching recommendation as an article with title and body', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: {}, // multiple critical recommendations fire
		} );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		// At least the "Add shipping tracking for every order" recommendation
		// (id: shipping-tracking-add) fires with the seed catalog.
		expect(
			screen.getByRole( 'heading', {
				name: /add shipping tracking for every order/i,
			} )
		).toBeInTheDocument();
		expect(
			screen.getByText( /rarely won without a tracking number/i )
		).toBeInTheDocument();
	} );

	it( 'applies the critical-urgency modifier class to articles whose entry is critical', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: {},
		} );

		const { container } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);

		const critical = container.querySelectorAll(
			'.dispute-recommendations__item--critical'
		);
		expect( critical.length ).toBeGreaterThan( 0 );
	} );

	it( 'applies the tip-urgency modifier class to entries whose urgency is tip', () => {
		const dispute = buildDispute( {
			status: 'won',
			reason: 'fraudulent',
			metadata: { __product_type: 'physical_product' },
			// refund_policy missing → "Tip: Upload your refund policy next time" fires.
			evidence: {},
		} );

		const { container } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);

		const tipItems = container.querySelectorAll(
			'.dispute-recommendations__item--tip'
		);
		expect( tipItems.length ).toBeGreaterThan( 0 );
	} );

	it( 'renders an external link when the recommendation has one', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: {},
		} );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		// The "shipping-tracking-add" entry has a link labeled
		// "Configure shipping tracking".
		const link = screen.getByRole( 'link', {
			name: /configure shipping tracking/i,
		} );
		expect( link ).toBeInTheDocument();
		expect( link.getAttribute( 'href' ) ).toBeTruthy();
	} );
} );
