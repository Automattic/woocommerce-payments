/** @format */

// This must mirror PR 218145's response contract. When the endpoint changes,
// update this file and tests/unit/fixtures/reports-balance-summary.php together.

const balanceSummaryFixture = {
	currency: 'usd',
	period: {
		start: '2024-03-01T00:00:00',
		end: '2024-03-31T23:59:59',
	},
	starting_balance: {
		amount: 1000,
	},
	total_charges_captured: {
		amount: 162672,
		count: 8,
	},
	fees: {
		amount: -6064,
	},
	charge_fees: {
		amount: -5958,
	},
	payout_fees: {
		amount: -100,
	},
	reader_fees: {
		amount: -150,
	},
	dispute_fees: {
		amount: -1500,
	},
	fee_refunds: {
		amount: 1644,
	},
	refunds: {
		amount: -21500,
		count: 3,
	},
	refund_failure: {
		amount: 11500,
		count: 1,
	},
	disputes: {
		amount: -38000,
		count: 1,
	},
	financing_payout: {
		amount: 1000000,
		count: 1,
	},
	financing_paydown: {
		amount: -7500,
		count: 1,
	},
	network_costs: {
		amount: -250,
		count: 2,
	},
	other_adjustments: {
		amount: 750,
		count: 1,
	},
	net_balance_change_in_the_period: {
		amount: 1101608,
	},
	payouts: {
		amount: 1102608,
		count: 3,
	},
	ending_balance: {
		amount: 0,
	},
};

export default balanceSummaryFixture;
