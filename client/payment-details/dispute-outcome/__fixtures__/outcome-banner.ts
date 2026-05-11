/** @format */

/**
 * Hand-built fixtures for the OutcomeBanner component. Shaped to mirror the
 * `Dispute` and `Charge` types from `wcpay/types/disputes` and
 * `wcpay/types/charges`, but only fields the banner reads are populated.
 *
 * Note on the Won case: in production, `dispute.balance_transactions[]` ships
 * with null amounts on the reversal row due to a server-side bug tracked as
 * RSM-1168. The fixture below populates realistic reversal amounts so the
 * visual reads correctly; the real-data wiring (deferred to a follow-up)
 * either waits on RSM-1168 or renders a fallback when amounts are missing.
 */

/**
 * Internal dependencies
 */
import type { Charge } from 'wcpay/types/charges';
import type { Dispute } from 'wcpay/types/disputes';

const baseEvidenceDetails = {
	has_evidence: true,
	due_by: 1714780800,
	past_due: false,
	submission_count: 1,
};

const cardCharge = ( issuer: string ): Charge =>
	( {
		id: 'ch_test_outcome_banner',
		amount: 5000,
		amount_captured: 5000,
		amount_refunded: 0,
		application_fee_amount: 0,
		balance_transaction: {
			amount: 5000,
			currency: 'usd',
			fee: 145,
		},
		billing_details: {
			email: 'buyer@example.com',
			name: 'Jane Buyer',
			phone: null,
			address: {
				city: null,
				country: null,
				line1: null,
				line2: null,
				postal_code: null,
				state: null,
			},
		},
		captured: true,
		created: 1714003200,
		dispute: null,
		disputed: true,
		order: null,
		outcome: null,
		paid: true,
		paydown: null,
		payment_intent: 'pi_test_outcome_banner',
		payment_method: 'pm_test_card',
		payment_method_details: {
			type: 'card',
			card: { issuer },
		},
		refunded: false,
		refunds: null,
		status: 'succeeded',
	} as unknown as Charge );

const klarnaCharge = (): Charge =>
	( {
		...cardCharge( 'Chase' ),
		payment_method_details: {
			type: 'klarna',
			klarna: {},
		},
	} as unknown as Charge );

const baseDispute = (): Omit<
	Dispute,
	'status' | 'balance_transactions' | 'created'
> => ( {
	id: 'dp_test_outcome_banner',
	evidence_details: baseEvidenceDetails,
	metadata: {},
	order: null,
	evidence: {},
	issuer_evidence: null,
	reason: 'fraudulent',
	charge: cardCharge( 'Chase' ),
	amount: 5000,
	currency: 'usd',
	payment_intent: 'pi_test_outcome_banner',
} );

export const wonFixture: { dispute: Dispute; charge: Charge } = {
	dispute: {
		...baseDispute(),
		status: 'won',
		created: 1714003200, // 2024-04-25 UTC
		// Reversal carries the decision moment and reinstated funds.
		// In production these arrive with null amounts (RSM-1168); the
		// fixture populates them so the visual reads correctly.
		balance_transactions: [
			{
				currency: 'usd',
				amount: -5000,
				fee: 1500,
				reporting_category: 'dispute',
				created: 1714003200,
			},
			{
				currency: 'usd',
				amount: 5000,
				fee: -1500,
				reporting_category: 'dispute_reversal',
				created: 1715040000, // 2024-05-07 UTC
			},
		],
	},
	charge: cardCharge( 'Chase' ),
};

export const lostFixture: { dispute: Dispute; charge: Charge } = {
	dispute: {
		...baseDispute(),
		status: 'lost',
		reason: 'product_unacceptable',
		created: 1714003200,
		balance_transactions: [
			{
				currency: 'usd',
				amount: -5000,
				fee: 1500,
				reporting_category: 'dispute',
				created: 1714780800, // 2024-05-04 UTC
			},
		],
	},
	charge: cardCharge( 'Wells Fargo' ),
};

export const warningClosedFixture: { dispute: Dispute; charge: Charge } = {
	dispute: {
		...baseDispute(),
		status: 'warning_closed',
		reason: 'general',
		created: 1714003200, // fallback when no balance_transactions
		balance_transactions: [],
	},
	// BNPL path exercises the getBankName fallback in the issuer slot.
	charge: klarnaCharge(),
};
