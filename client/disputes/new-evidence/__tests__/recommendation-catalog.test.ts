/**
 * Internal dependencies
 */
import { getRecommendations } from '../recommendations';
import {
	RECOMMENDATIONS_CATALOG,
	WIZARD_SUBMITTABLE_EVIDENCE_KEYS,
} from '../recommendation-catalog';
import type { RecommendationContext } from '../types';

const context = (
	overrides: Partial< RecommendationContext > = {}
): RecommendationContext => ( {
	reason: 'product_not_received',
	productType: 'physical_product',
	outcome: 'keep_doing',
	evidence: {},
	...overrides,
} );

const ids = ( ctx: RecommendationContext ): string[] =>
	getRecommendations( ctx, RECOMMENDATIONS_CATALOG ).map( ( r ) => r.id );

const criticalIds = ( ctx: RecommendationContext ): string[] =>
	getRecommendations( ctx, RECOMMENDATIONS_CATALOG )
		.filter( ( r ) => r.urgency === 'critical' )
		.map( ( r ) => r.id );

// Smoke coverage for the real RECOMMENDATIONS_CATALOG. The matcher's unit tests
// use fixtures, so these exercise the actual `when` predicates and fail CI on a
// catalog authoring error.
describe( 'RECOMMENDATIONS_CATALOG runtime behavior', () => {
	describe( 'cluster 15 "no evidence" catch-all', () => {
		it( 'fires and is the only critical when no evidence is provided', () => {
			const ctx = context( {
				outcome: 'could_help',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: {},
			} );

			expect( ids( ctx ) ).toContain( 'c15-no-evidence-submit' );
			// suppressOtherCriticals leaves exactly one critical on the card.
			expect( criticalIds( ctx ) ).toEqual( [
				'c15-no-evidence-submit',
			] );
		} );

		it( 'still fires when only the auto-populated customer IP is present', () => {
			// Regression guard: customer_purchase_ip is auto-set from the order
			// IP, so if it leaks into WIZARD_SUBMITTABLE_EVIDENCE_KEYS, c15's
			// `max: 0` gate stops firing.
			const ctx = context( {
				outcome: 'could_help',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: { customer_purchase_ip: '203.0.113.5' },
			} );

			expect( ids( ctx ) ).toContain( 'c15-no-evidence-submit' );
			expect( criticalIds( ctx ) ).toEqual( [
				'c15-no-evidence-submit',
			] );
		} );

		it( 'still fires when only the auto-generated cover letter is present', () => {
			// Regression guard: the wizard auto-generates and submits the cover
			// letter (uncategorized_text) by default, so c15 must ignore it or it
			// never fires for a merchant who sent no real evidence.
			const ctx = context( {
				outcome: 'could_help',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: {
					uncategorized_text: 'Dear Dispute Resolution Team, ...',
				},
			} );

			expect( ids( ctx ) ).toContain( 'c15-no-evidence-submit' );
			expect( criticalIds( ctx ) ).toEqual( [
				'c15-no-evidence-submit',
			] );
		} );

		it( 'does not fire once real evidence is present', () => {
			const ctx = context( {
				outcome: 'could_help',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: { receipt: 'receipt.pdf' },
			} );

			expect( ids( ctx ) ).not.toContain( 'c15-no-evidence-submit' );
		} );
	} );

	describe( 'representative cluster matches', () => {
		it( 'surfaces the strong-shipping positive on a won PNR with tracking + carrier', () => {
			const ctx = context( {
				outcome: 'keep_doing',
				reason: 'product_not_received',
				productType: 'physical_product',
				evidence: {
					shipping_tracking_number: '1Z999',
					shipping_carrier: 'UPS',
				},
			} );

			expect( ids( ctx ) ).toContain( 'c1-shipping-evidence-strong' );
			// keep_doing outcomes are positives/tips only; no critical ever
			// renders for a won dispute.
			expect( criticalIds( ctx ) ).toEqual( [] );
		} );

		it( 'surfaces the cancellation critical on a lost subscription dispute missing both fields', () => {
			const ctx = context( {
				outcome: 'could_help',
				reason: 'subscription_canceled',
				productType: 'other',
				// A non-cancellation field keeps c15 from firing so the
				// cluster-6 critical is observable.
				evidence: { receipt: 'receipt.pdf' },
			} );

			expect( ids( ctx ) ).toContain( 'c6-cancellation-document' );
		} );

		it( 'coaches documenting the duplicate charge when the doc is missing', () => {
			const ctx = context( {
				outcome: 'could_help',
				reason: 'duplicate',
				productType: 'physical_product',
				// A non-duplicate field keeps c15 from firing so c7 is observable.
				evidence: { receipt: 'receipt.pdf' },
			} );

			expect( ids( ctx ) ).toContain( 'c7-duplicate-charge-explain' );
		} );

		it( 'surfaces the duplicate-charge positive when documentation is present on a won dispute', () => {
			const ctx = context( {
				outcome: 'keep_doing',
				reason: 'duplicate',
				productType: 'physical_product',
				evidence: { duplicate_charge_documentation: 'doc.pdf' },
			} );

			expect( ids( ctx ) ).toContain( 'c7-duplicate-charge-explained' );
		} );
	} );

	describe( 'no critical recommendation ever renders for a won dispute', () => {
		// Every critical gates on `outcome: could_help`, so none should appear
		// for a won dispute. Assert the invariant against the real catalog.
		it( 'returns zero criticals for any keep_doing context', () => {
			// Every reason any entry gates on, sourced from the catalog so a
			// future entry on a new reason is covered automatically.
			const reasons = [
				...new Set(
					RECOMMENDATIONS_CATALOG.flatMap(
						( entry ) => entry.when.reasonIn
					)
				),
			];
			reasons.forEach( ( reason ) => {
				expect(
					criticalIds(
						context( {
							outcome: 'keep_doing',
							reason,
							evidence: {},
						} )
					)
				).toEqual( [] );
			} );
		} );
	} );
} );

// Hygiene guards for the hand-maintained key set behind c15. The wizard builds
// its evidence object dynamically, so there's no static source of truth to
// cross-check; instead guard the failure mode that bit us: an always-present
// field slipping into the list and defeating c15.
describe( 'WIZARD_SUBMITTABLE_EVIDENCE_KEYS hygiene', () => {
	// Always-present fields (auto-populated, or placeholder-defaulted like
	// product_description) must stay out of the key set or c15 never fires.
	const alwaysPresentFields = [
		'customer_purchase_ip',
		'customer_name',
		'customer_email_address',
		'billing_address',
		'product_description',
	];

	it( 'contains no always-present (auto-populated or defaulted) fields', () => {
		const leaked = WIZARD_SUBMITTABLE_EVIDENCE_KEYS.filter( ( key ) =>
			alwaysPresentFields.includes( key )
		);
		expect( leaked ).toEqual( [] );
	} );

	it( 'has no duplicate keys', () => {
		const duplicates = WIZARD_SUBMITTABLE_EVIDENCE_KEYS.filter(
			( key, index ) =>
				WIZARD_SUBMITTABLE_EVIDENCE_KEYS.indexOf( key ) !== index
		);
		expect( duplicates ).toEqual( [] );
	} );

	// Keys a predicate may reference even though the wizard does not persist
	// them. Empty today; add a key here (with a reason) only if a recommendation
	// must gate on a field collected outside the response wizard.
	const allowedNonWizardKeys = new Set< string >( [] );

	it( 'gates every predicate on a key the wizard can persist', () => {
		const predicateKeys = RECOMMENDATIONS_CATALOG.flatMap( ( entry ) => [
			...( entry.when.requireProvided?.keys ?? [] ),
			...( entry.when.requireMissing?.keys ?? [] ),
		] );
		const unpersistable = [ ...new Set( predicateKeys ) ].filter(
			( key ) =>
				! WIZARD_SUBMITTABLE_EVIDENCE_KEYS.includes( key ) &&
				! allowedNonWizardKeys.has( key )
		);
		// A non-empty list means a recommendation gates on a field the wizard
		// never saves. Add it to the wizard's save path, or to
		// allowedNonWizardKeys above if it is intentionally collected elsewhere.
		expect( unpersistable ).toEqual( [] );
	} );
} );
