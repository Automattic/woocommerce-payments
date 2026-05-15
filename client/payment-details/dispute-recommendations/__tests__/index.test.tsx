/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';

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

// "Won + PNR + physical with PARTIAL evidence" — triggers positives from
// Clusters 1/2/3 plus tips from any cluster whose `requireMissing` field
// is absent. Used for tests that need both sections to render.
const wonPhysicalShippingProvided = (
	extra: Partial< ChargeDispute > = {}
): ChargeDispute =>
	buildDispute( {
		status: 'won',
		reason: 'product_not_received',
		metadata: { __product_type: 'physical_product' },
		evidence: {
			shipping_tracking_number: '1Z999',
			shipping_carrier: 'UPS',
			shipping_date: '2026-04-15',
			shipping_address: '123 Main St',
			receipt: 'receipt-url',
			customer_communication: 'thread',
		},
		...extra,
	} );

// "Won + PNR + physical with FULL evidence" — every field Cluster 1/2/3/11a
// tips check for is provided, so only positives fire. Used for the single-
// section test.
const wonPhysicalFullEvidence = (): ChargeDispute =>
	buildDispute( {
		status: 'won',
		reason: 'product_not_received',
		metadata: { __product_type: 'physical_product' },
		evidence: {
			shipping_tracking_number: '1Z999',
			shipping_carrier: 'UPS',
			shipping_date: '2026-04-15',
			shipping_address: '123 Main St',
			shipping_documentation: 'tracked',
			customer_signature: 'signed.pdf',
			receipt: 'receipt-url',
			customer_communication: 'thread',
		},
	} );

describe( 'DisputeRecommendationsCard', () => {
	describe( 'section rendering', () => {
		it( 'renders the "What\'s working well" section when positives fire', () => {
			render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalShippingProvided() }
				/>
			);

			expect(
				screen.getByRole( 'heading', {
					name: /what's working well/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'renders the "What could help next time" section when critical/tip fire', () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: {}, // multiple critical recommendations fire
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'renders only sections that have matching entries', () => {
			// Won + PNR + physical with every applicable field provided:
			// positives fire, but every Tip's `requireMissing` clause fails →
			// only the "What's working well" section renders.
			render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalFullEvidence() }
				/>
			);

			expect(
				screen.getByRole( 'heading', {
					name: /what's working well/i,
				} )
			).toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).not.toBeInTheDocument();
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

		it( 'returns null when no catalog entries match the dispute', () => {
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
	} );

	describe( 'urgency styling', () => {
		it( 'applies the positive modifier class on Keep Doing entries', () => {
			const { container } = render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalShippingProvided() }
				/>
			);

			expect(
				container.querySelectorAll(
					'.dispute-recommendations__item--positive'
				).length
			).toBeGreaterThan( 0 );
		} );

		it( 'applies the critical modifier class on Critical entries', () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'receipt-url' }, // limit firing to leave some criticals
			} );

			const { container } = render(
				<DisputeRecommendationsCard dispute={ dispute } />
			);

			expect(
				container.querySelectorAll(
					'.dispute-recommendations__item--critical'
				).length
			).toBeGreaterThan( 0 );
		} );

		it( 'applies the tip modifier class on Tip entries', () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				// refund_policy missing → c5-refund-policy-publish-won tip fires.
				evidence: { service_date: '2026-04-15' },
			} );

			const { container } = render(
				<DisputeRecommendationsCard dispute={ dispute } />
			);

			expect(
				container.querySelectorAll(
					'.dispute-recommendations__item--tip'
				).length
			).toBeGreaterThan( 0 );
		} );
	} );

	describe( 'capping at 3 per section with show-more', () => {
		it( 'shows at most 3 entries inline per section', () => {
			// Lost + PNR + physical, no evidence at all: many criticals fire.
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: {},
			} );

			const { container } = render(
				<DisputeRecommendationsCard dispute={ dispute } />
			);

			const section = container.querySelector(
				'.dispute-recommendations-card__section'
			);
			expect( section ).not.toBeNull();
			// Direct children of the section that are recommendation items
			// (excludes the <details> show-more, which wraps the rest).
			const inlineItems = section?.querySelectorAll(
				':scope > .dispute-recommendations__item'
			);
			expect( inlineItems?.length ).toBeLessThanOrEqual( 3 );
		} );

		it( 'wraps overflow entries beyond 3 in a <details> show-more disclosure', () => {
			// Lost + PNR + physical with only `receipt` provided. `receipt`
			// dodges Cluster 15 suppression, so "What could help next time"
			// gets four entries in catalog order: three criticals (c1 shipping
			// tracking, c3 customer correspondence, c11a signed delivery proof)
			// and one tip (c12 cover letter). With VISIBLE_PER_SECTION = 3, the
			// fourth entry deterministically overflows into the disclosure.
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'r' },
			} );

			const { container } = render(
				<DisputeRecommendationsCard dispute={ dispute } />
			);

			const details = container.querySelector(
				'.dispute-recommendations-card__show-more'
			);
			expect( details ).not.toBeNull();
			expect( details?.tagName ).toBe( 'DETAILS' );

			// The single overflow entry (c12, last in catalog order) is the
			// one inside the disclosure, and the summary advertises it.
			const disclosure = details as HTMLElement;
			expect(
				within( disclosure ).getByText( /show 1 more/i )
			).toBeInTheDocument();
			expect(
				within( disclosure ).getByRole( 'heading', {
					name: /include a cover letter with your evidence/i,
				} )
			).toBeInTheDocument();
		} );
	} );

	describe( 'Cluster 8 / 8b product-type scoping', () => {
		// Regression for a bug where Cluster 8 (service_date) entries fired on
		// fraudulent + physical_product. The wizard collects `shipping_date`
		// (not `service_date`) for physical, so the recommendation coached
		// merchants on a field they could not reach. Cluster 8b mirrors
		// Cluster 8 for physical, keyed off shipping_date.
		it( 'does not fire the service_date tip on fraudulent + physical_product', () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: { shipping_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.queryByRole( 'heading', {
					name: /document the service date/i,
				} )
			).not.toBeInTheDocument();
			expect(
				screen.queryByRole( 'heading', {
					name: /include the service date/i,
				} )
			).not.toBeInTheDocument();
		} );

		it( 'still fires service_date entries on fraudulent + digital_product_or_service', () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'fraudulent',
				metadata: { __product_type: 'digital_product_or_service' },
				evidence: { receipt: 'r' }, // dodge c15 suppression
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', {
					name: /include the service date/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'fires the shipping_date positive on fraudulent + physical when shipping_date is provided (won)', () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: { shipping_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', {
					name: /shipping date on record/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'fires the shipping_date tip on fraudulent + physical when shipping_date is missing (lost)', () => {
			// Cluster 8b ships positive + tip only (no Critical), per RiskOps:
			// shipping date doesn't prove the cardholder made the purchase, so
			// it's worth surfacing as a tip, not a critical.
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'r' }, // dodge c15 suppression
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.getByRole( 'heading', {
					name: /document the shipping date/i,
				} )
			).toBeInTheDocument();
			// And there is no longer a Critical "Include the shipping date".
			expect(
				screen.queryByRole( 'heading', {
					name: /include the shipping date/i,
				} )
			).not.toBeInTheDocument();
		} );

		it( 'does not fire shipping_date entries on fraudulent + digital', () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'digital_product_or_service' },
				evidence: { shipping_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			expect(
				screen.queryByRole( 'heading', {
					name: /shipping date on record/i,
				} )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'Cluster 15 suppression', () => {
		it( 'shows the catch-all critical and suppresses other criticals when no evidence is submitted', () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: {}, // truly empty → c15 fires
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			// c15 title visible
			expect(
				screen.getByRole( 'heading', {
					name: /submit evidence with your dispute response/i,
				} )
			).toBeInTheDocument();
			// Other criticals (e.g. c1 shipping tracking) suppressed
			expect(
				screen.queryByRole( 'heading', {
					name: /add shipping tracking for every order/i,
				} )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'link rendering', () => {
		// Per RiskOps review: per-rec action links were removed in favor of a
		// single "Learn more" link next to the "What could help next time"
		// header, to align with WooPayments admin's restrained vocabulary.
		it( 'renders a "Learn more" link in the "What could help next time" section header', () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'r' }, // dodge c15 suppression
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			const link = screen.getByRole( 'link', { name: /learn more/i } );
			expect( link ).toBeInTheDocument();
			expect( link ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/managing-payment-disputes/'
			);
		} );

		it( 'does not render the "Learn more" link in the "What\'s working well" section', () => {
			// Won dispute that fires positives only: the Learn more link is
			// scoped to "What could help next time" and shouldn't appear here.
			render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalFullEvidence() }
				/>
			);

			expect(
				screen.queryByRole( 'link', { name: /learn more/i } )
			).not.toBeInTheDocument();
		} );
	} );
} );
