/** @format **/

/**
 * Internal dependencies
 */
import { getDisputeRecommendations } from '../utils';
import { getRecommendations } from 'wcpay/disputes/new-evidence/recommendations';
import { RECOMMENDATIONS_CATALOG } from 'wcpay/disputes/new-evidence/recommendation-catalog';
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

const wonPhysicalShippingProvided = (): ChargeDispute =>
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
	} );

describe( 'getDisputeRecommendations', () => {
	// Shared by the card (what renders) and the wrapper's `has_recommendations`
	// flag, so the two can't disagree. Equivalence with a direct
	// getRecommendations call locks the status -> outcome mapping in place.
	it( 'maps a lost dispute to could_help and delegates to getRecommendations', () => {
		const dispute = buildDispute( {
			status: 'lost',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
			evidence: { receipt: 'r' },
		} );

		expect(
			getDisputeRecommendations( dispute, 'physical_product' )
		).toEqual(
			getRecommendations(
				{
					reason: 'product_not_received',
					productType: 'physical_product',
					outcome: 'could_help',
					evidence: dispute.evidence,
				},
				RECOMMENDATIONS_CATALOG
			)
		);
	} );

	it( 'maps a won dispute to keep_doing', () => {
		const dispute = wonPhysicalShippingProvided();

		expect(
			getDisputeRecommendations( dispute, 'physical_product' )
		).toEqual(
			getRecommendations(
				{
					reason: dispute.reason,
					productType: 'physical_product',
					outcome: 'keep_doing',
					evidence: dispute.evidence,
				},
				RECOMMENDATIONS_CATALOG
			)
		);
	} );

	it( 'returns an empty array for a warning_closed dispute (no outcome)', () => {
		const dispute = buildDispute( {
			status: 'warning_closed',
			reason: 'product_not_received',
			metadata: { __product_type: 'physical_product' },
		} );

		expect(
			getDisputeRecommendations( dispute, 'physical_product' )
		).toEqual( [] );
	} );
} );
