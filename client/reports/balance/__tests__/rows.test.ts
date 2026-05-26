/** @format */

/**
 * Internal dependencies
 */
import { BALANCE_ROWS, getVisibleBalanceRows } from '../rows';

describe( 'Balance report rows', () => {
	it( 'defines the Balance rows in display order', () => {
		// The UI row order mirrors the endpoint contract documented in
		// includes/core/server/request/class-get-reporting-balance-summary.md.
		expect( BALANCE_ROWS.map( ( row ) => row.key ) ).toEqual( [
			'starting_balance',
			'total_charges_captured',
			'fees',
			'charge_fees',
			'dispute_fees',
			'fee_refunds',
			'refunds',
			'refund_failure',
			'disputes',
			'financing_payout',
			'financing_paydown',
			'payout_fees',
			'reader_fees',
			'network_costs',
			'other_adjustments',
			'net_balance_change_in_the_period',
			'payouts',
			'ending_balance',
		] );
	} );

	it( 'marks only the required anchor rows as always visible', () => {
		expect(
			BALANCE_ROWS.filter( ( row ) => row.alwaysVisible === true ).map(
				( row ) => row.key
			)
		).toEqual( [
			'starting_balance',
			'total_charges_captured',
			'fees',
			'net_balance_change_in_the_period',
			'payouts',
			'ending_balance',
		] );
	} );

	it( 'defaults missing row amounts to zero', () => {
		const refunds = BALANCE_ROWS.find( ( row ) => row.key === 'refunds' );

		expect( refunds?.getAmount( {} ) ).toBe( 0 );
		expect( refunds?.getCount?.( {} ) ).toBeUndefined();
	} );

	it( 'hides optional zero rows while keeping anchor rows visible', () => {
		const zeroSummary = BALANCE_ROWS.reduce(
			( summary, row ) => ( {
				...summary,
				[ row.key ]: { amount: 0, count: 0 },
			} ),
			{}
		);

		expect(
			getVisibleBalanceRows( zeroSummary ).map( ( row ) => row.key )
		).toEqual( [
			'starting_balance',
			'total_charges_captured',
			'fees',
			'net_balance_change_in_the_period',
			'payouts',
			'ending_balance',
		] );
	} );

	it( 'keeps optional rows visible when they have an amount or count', () => {
		const zeroAmountWithCount = {
			network_costs: {
				amount: 0,
				count: 2,
			},
		};

		expect(
			getVisibleBalanceRows( zeroAmountWithCount ).map(
				( row ) => row.key
			)
		).toContain( 'network_costs' );
	} );
} );
