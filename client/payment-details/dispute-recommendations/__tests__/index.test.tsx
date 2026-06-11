/** @format **/

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within, fireEvent } from '@testing-library/react';
import user from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import DisputeRecommendationsCard from '../index';
import { sortByLift } from '../utils';
import { getRecommendations } from 'wcpay/disputes/new-evidence/recommendations';
import { RECOMMENDATIONS_CATALOG } from 'wcpay/disputes/new-evidence/recommendation-catalog';
import { recordEvent } from 'wcpay/tracks';
import { _resetOutcomeViewTrackingForTests } from '../../dispute-outcome/tracks';
import type { ChargeDispute } from 'wcpay/types/charges';

jest.mock( 'wcpay/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const sectionViewedEvent =
	'wcpay_dispute_outcome_recommendations_section_viewed';
const sectionViewedCalls = () =>
	( recordEvent as jest.Mock ).mock.calls.filter(
		( [ name ] ) => name === sectionViewedEvent
	);

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

// Cards collapse by default; items, modifiers, and the "Learn more" link
// only enter the DOM after the toggle is clicked.
const expandSection = ( name: RegExp ) =>
	user.click( screen.getByRole( 'button', { name } ) );

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
			const dispute = wonPhysicalFullEvidence();

			// Invariant guard: the rest of this test depends on the fixture
			// satisfying every requireMissing clause for won/PNR/physical.
			// If a new catalog entry introduces a key the fixture doesn't
			// cover, fail here with the offending ids — clearer than the
			// "unexpected heading" failure below.
			const unexpected = getRecommendations(
				{
					reason: dispute.reason,
					productType: 'physical_product',
					outcome: 'keep_doing',
					evidence: dispute.evidence,
				},
				RECOMMENDATIONS_CATALOG
			)
				.filter( ( r ) => r.urgency !== 'positive' )
				.map( ( r ) => r.id );
			expect( unexpected ).toEqual( [] );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

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
		// Target a stable known item per urgency rather than counting matches:
		// counts shift as the catalog grows; the modifier class is the
		// behavior under test.

		it( 'applies the positive modifier class on Keep Doing entries', async () => {
			render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalShippingProvided() }
				/>
			);

			await expandSection( /what's working well/i );

			// c1-shipping-tracking-positive fires on this fixture (shipping
			// tracking number + carrier are both provided).
			expect(
				screen
					.getByRole( 'heading', {
						name: /strong shipping evidence/i,
					} )
					.closest( 'article' )
			).toHaveClass( 'dispute-recommendations__item--positive' );
		} );

		it( 'applies the critical modifier class on Critical entries', async () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'receipt-url' }, // dodge c15 suppression
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			// c1-shipping-tracking-critical fires on this fixture (shipping
			// tracking number is missing for a physical PNR dispute).
			expect(
				screen
					.getByRole( 'heading', {
						name: /add shipping tracking for every order/i,
					} )
					.closest( 'article' )
			).toHaveClass( 'dispute-recommendations__item--critical' );
		} );

		it( 'applies the tip modifier class on Tip entries', async () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				// c3-communication-consider (no customer_communication) and
				// c8b-shipping-date-document (no shipping_date for physical +
				// fraudulent) both fire as tips. c5 is excluded by its
				// reasonIn list.
				evidence: { service_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			expect(
				screen
					.getByRole( 'heading', {
						name: /document the shipping date/i,
					} )
					.closest( 'article' )
			).toHaveClass( 'dispute-recommendations__item--tip' );
		} );
	} );

	describe( 'capping at 3 per section with show-more', () => {
		it( 'caps inline entries at exactly 3 per section', async () => {
			// Lost + PNR + physical with only `receipt` provided dodges Cluster
			// 15 suppression and yields four "What could help next time" entries
			// (three criticals + one tip). With VISIBLE_PER_SECTION = 3, exactly
			// three render inline and the fourth overflows into the disclosure,
			// so this stresses the cap rather than passing trivially. (Using
			// `evidence: {}` would let Cluster 15 suppress down to two entries,
			// never exercising the cap at all.)
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'r' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			// Each recommendation renders as an <article>; <details> wraps the
			// overflow. This fixture renders only the coaching section, so
			// counting articles outside any <details> globally is enough to
			// assert the cap.
			expect(
				screen.getByRole( 'heading', {
					name: /what could help next time/i,
				} )
			).toBeInTheDocument();
			const inlineItems = screen
				.getAllByRole( 'article' )
				.filter( ( el ) => ! el.closest( 'details' ) );
			expect( inlineItems ).toHaveLength( 3 );
		} );

		it( 'wraps overflow entries beyond 3 in a <details> show-more disclosure', async () => {
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

			await expandSection( /what could help next time/i );

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

	describe( 'Cluster 8b shipping_date product-type scoping', () => {
		// Cluster 8b coaches on shipping_date for physical + fraudulent, keyed
		// off the field the wizard actually collects. The sibling Cluster 8
		// (service_date) is deferred upstream until the wizard collects it, so
		// the previous service_date regression tests live on the catalog side.
		it( 'fires the shipping_date positive on fraudulent + physical when shipping_date is provided (won)', async () => {
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'physical_product' },
				evidence: { shipping_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what's working well/i );

			expect(
				screen.getByRole( 'heading', {
					name: /shipping date on record/i,
				} )
			).toBeInTheDocument();
		} );

		it( 'fires the shipping_date tip on fraudulent + physical when shipping_date is missing (lost)', async () => {
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

			await expandSection( /what could help next time/i );

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

		it( 'does not fire shipping_date entries on fraudulent + digital', async () => {
			// Won on this fixture: c3-communication-consider and
			// c10-access-log-add fire as tips in the coaching section. Expand
			// it so the shipping_date absence check isn't trivially true from
			// the section being collapsed.
			const dispute = buildDispute( {
				status: 'won',
				reason: 'fraudulent',
				metadata: { __product_type: 'digital_product_or_service' },
				evidence: { shipping_date: '2026-04-15' },
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			expect(
				screen.queryByRole( 'heading', {
					name: /shipping date on record/i,
				} )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'Cluster 15 suppression', () => {
		it( 'shows the catch-all critical and suppresses other criticals when no evidence is submitted', async () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: {}, // truly empty → c15 fires
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

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

		it( 'does not also surface the c12 cover-letter tip when no evidence is submitted', async () => {
			// c15's `suppressOtherCriticals` only hides criticals, not tips, so
			// without c12's `requireProvided` guard the cover-letter tip would
			// render alongside c15's "submit evidence" message and read as
			// redundant. Verify c12 stays silent on the truly-empty path.
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: {}, // truly empty → c15 fires, c12 must not
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			expect(
				screen.queryByRole( 'heading', {
					name: /include a cover letter with your evidence/i,
				} )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'link rendering', () => {
		// Per RiskOps review: per-rec action links were removed in favor of a
		// single "Learn more" link next to the "What could help next time"
		// header, to align with WooPayments admin's restrained vocabulary.
		it( 'renders a "Learn more" link in the "What could help next time" section header', async () => {
			const dispute = buildDispute( {
				status: 'lost',
				reason: 'product_not_received',
				metadata: { __product_type: 'physical_product' },
				evidence: { receipt: 'r' }, // dodge c15 suppression
			} );

			render( <DisputeRecommendationsCard dispute={ dispute } /> );

			await expandSection( /what could help next time/i );

			const link = screen.getByRole( 'link', { name: /learn more/i } );
			expect( link ).toBeInTheDocument();
			expect( link ).toHaveAttribute(
				'href',
				'https://woocommerce.com/document/managing-payment-disputes/'
			);
		} );

		it( 'does not render the "Learn more" link in the "What\'s working well" section', async () => {
			// Won dispute that fires positives only: the Learn more link is
			// scoped to "What could help next time" and shouldn't appear here.
			// Expand the strengths card so the absence check isn't trivially
			// satisfied by the section being collapsed.
			render(
				<DisputeRecommendationsCard
					dispute={ wonPhysicalFullEvidence() }
				/>
			);

			await expandSection( /what's working well/i );

			expect(
				screen.queryByRole( 'link', { name: /learn more/i } )
			).not.toBeInTheDocument();
		} );
	} );
} );

describe( 'Event 2: recommendations_section_viewed', () => {
	beforeEach( () => {
		( recordEvent as jest.Mock ).mockClear();
		_resetOutcomeViewTrackingForTests();
	} );

	it( 'fires once for the coaching section with section key, counts and ids', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: { receipt: 'r' },
		} );
		// Sorted-by-lift — the same order the card's effect emits them in.
		const expectedIds = [
			...getRecommendations(
				{
					reason: 'product_not_received',
					productType: 'physical_product',
					outcome: 'could_help',
					evidence: { receipt: 'r' },
				},
				RECOMMENDATIONS_CATALOG
			),
		]
			.sort( sortByLift )
			.map( ( r ) => r.id );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		const calls = sectionViewedCalls();
		expect( calls ).toHaveLength( 1 );
		expect( calls[ 0 ][ 1 ] ).toEqual(
			expect.objectContaining( {
				section: 'what_could_help',
				recommendation_count: expectedIds.length,
				visible_count: Math.min( 3, expectedIds.length ),
			} )
		);
		// Exact order, not just membership: the emit reads the same sorted
		// list RecommendationSection renders, so ids track render order.
		expect( calls[ 0 ][ 1 ].recommendation_ids ).toEqual( expectedIds );
	} );

	it( 'fires for both sections when both render', () => {
		render(
			<DisputeRecommendationsCard
				dispute={ wonPhysicalShippingProvided() }
			/>
		);

		const sections = sectionViewedCalls().map(
			( [ , props ] ) => props.section
		);
		expect( sections ).toHaveLength( 2 );
		expect( sections ).toEqual(
			expect.arrayContaining( [
				'whats_working_well',
				'what_could_help',
			] )
		);
	} );

	it( 'dedups per section across unmount/remount of the same dispute', () => {
		const dispute = wonPhysicalShippingProvided();
		const { unmount } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);
		expect( sectionViewedCalls() ).toHaveLength( 2 );

		// Production regression: payment-details remounts the card during
		// loading; the module-scoped Set must keep this at one per section.
		unmount();
		render( <DisputeRecommendationsCard dispute={ dispute } /> );

		expect( sectionViewedCalls() ).toHaveLength( 2 );
	} );

	it( 'fires no section event when the card renders nothing', () => {
		render(
			<DisputeRecommendationsCard
				dispute={ buildDispute( {
					status: 'warning_closed',
					reason: 'product_not_received',
					metadata: { __product_type: 'physical_product' },
				} ) }
			/>
		);

		expect( sectionViewedCalls() ).toHaveLength( 0 );
	} );
} );

describe( 'Event 4: action_clicked', () => {
	const actionCalls = () =>
		( recordEvent as jest.Mock ).mock.calls.filter(
			( [ name ] ) => name === 'wcpay_dispute_outcome_action_clicked'
		);

	beforeEach( () => {
		( recordEvent as jest.Mock ).mockClear();
		_resetOutcomeViewTrackingForTests();
	} );

	it( 'fires learn_more_clicked when the "Learn more" link is clicked', async () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: { receipt: 'r' },
		} );

		render( <DisputeRecommendationsCard dispute={ dispute } /> );
		await expandSection( /what could help next time/i );
		await user.click( screen.getByRole( 'link', { name: /learn more/i } ) );

		expect( actionCalls() ).toHaveLength( 1 );
		expect( actionCalls()[ 0 ][ 1 ] ).toEqual(
			expect.objectContaining( {
				action: 'learn_more_clicked',
				section: 'what_could_help',
				link_href:
					'https://woocommerce.com/document/managing-payment-disputes/',
			} )
		);
	} );

	it( 'fires show_more_expanded on expand only, not on collapse', async () => {
		// Four coaching entries (receipt dodges c15) overflow the cap-of-3 into
		// the show-more disclosure.
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: { receipt: 'r' },
		} );

		const { container } = render(
			<DisputeRecommendationsCard dispute={ dispute } />
		);
		await expandSection( /what could help next time/i );

		const details = container.querySelector(
			'.dispute-recommendations-card__show-more'
		) as HTMLDetailsElement;

		details.open = true;
		fireEvent( details, new Event( 'toggle' ) );
		expect( actionCalls() ).toHaveLength( 1 );
		expect( actionCalls()[ 0 ][ 1 ] ).toEqual(
			expect.objectContaining( {
				action: 'show_more_expanded',
				section: 'what_could_help',
			} )
		);

		// Collapsing must not fire a second event.
		details.open = false;
		fireEvent( details, new Event( 'toggle' ) );
		expect( actionCalls() ).toHaveLength( 1 );
	} );
} );
