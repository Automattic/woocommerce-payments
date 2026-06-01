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

// Smoke coverage for the real RECOMMENDATIONS_CATALOG. The matcher's own unit
// tests use synthetic fixtures; these exercise the actual `when` predicates so a
// catalog authoring error (wrong reason, inverted predicate, a stray key in
// WIZARD_SUBMITTABLE_EVIDENCE_KEYS) fails CI instead of shipping silently.
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
			// Regression guard: `customer_purchase_ip` is auto-set from the
			// order IP, not merchant-entered, so it must not count as evidence
			// for the catch-all. If it leaks back into
			// WIZARD_SUBMITTABLE_EVIDENCE_KEYS, c15's `max: 0` gate stops
			// firing and the merchant loses the "submit evidence" message.
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
	} );

	describe( 'no critical recommendation ever renders for a won dispute', () => {
		// Criticals are coaching for lost disputes only; the catalog encodes
		// this by gating every critical on `outcome: could_help`. Assert the
		// invariant holds across the real catalog rather than trusting it.
		it( 'returns zero criticals for any keep_doing context', () => {
			const reasons = [
				'product_not_received',
				'fraudulent',
				'product_unacceptable',
				'credit_not_processed',
				'duplicate',
				'subscription_canceled',
				'general',
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

// Hygiene guards for the hand-maintained key set that gates the c15 catch-all.
// There is no single static source of truth for "wizard-submittable fields" in
// the codebase (the wizard builds its evidence object dynamically), so we guard
// the specific failure mode that bit us: an auto-populated field that the
// merchant cannot fill in slipping into the list and defeating c15.
describe( 'WIZARD_SUBMITTABLE_EVIDENCE_KEYS hygiene', () => {
	// Mirrors the exclusion list documented in constants/high-impact-fields.ts.
	const autoPopulatedFields = [
		'customer_purchase_ip',
		'customer_name',
		'customer_email_address',
		'billing_address',
	];

	it( 'contains no auto-populated fields', () => {
		const leaked = WIZARD_SUBMITTABLE_EVIDENCE_KEYS.filter( ( key ) =>
			autoPopulatedFields.includes( key )
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
} );
