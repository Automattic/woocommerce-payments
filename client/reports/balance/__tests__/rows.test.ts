/** @format */

/**
 * Internal dependencies
 */
import balanceSummaryFixture from 'wcpay/data/reports/__tests__/balance-fixture';
import { BALANCE_ROWS, getVisibleBalanceRows, isAnchorRow } from '../rows';

jest.mock( 'wcpay/utils', () => ( {
	getAdminUrl: ( args: Record< string, unknown > ) => {
		const params = new URLSearchParams();
		Object.entries( args ).forEach( ( [ key, value ] ) => {
			if ( Array.isArray( value ) ) {
				value.forEach( ( item, index ) => {
					params.append( `${ key }[${ index }]`, String( item ) );
				} );
				return;
			}
			if ( value !== undefined ) {
				params.append( key, String( value ) );
			}
		} );
		return `admin.php?${ params.toString() }`;
	},
} ) );

const period = {
	start: '2026-05-01T00:00:00.000Z',
	end: '2026-05-14T23:59:59.999Z',
};

describe( 'Balance report rows', () => {
	it( 'defines the Balance rows in endpoint contract order', () => {
		expect( BALANCE_ROWS.map( ( row ) => row.key ) ).toEqual( [
			'starting_balance',
			'total_charges_captured',
			'fees',
			'charge_fees',
			'payout_fees',
			'reader_fees',
			'dispute_fees',
			'fee_refunds',
			'refunds',
			'refund_failure',
			'disputes',
			'financing_payout',
			'financing_paydown',
			'network_costs',
			'other_adjustments',
			'net_balance_change_in_the_period',
			'payouts',
			'ending_balance',
		] );
	} );

	it( 'marks only the required anchor rows as always visible', () => {
		expect(
			BALANCE_ROWS.filter( isAnchorRow ).map( ( row ) => row.key )
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

	it( 'builds Explore links for supported rows', () => {
		const links = Object.fromEntries(
			BALANCE_ROWS.map( ( row ) => [
				row.key,
				row.exploreLink?.( balanceSummaryFixture, period ),
			] )
		);

		expect( links.total_charges_captured ).toContain(
			'path=%2Fpayments%2Ftransactions'
		);
		expect( links.total_charges_captured ).toContain(
			'type_is_in%5B0%5D=charge'
		);
		expect( links.total_charges_captured ).toContain(
			'type_is_in%5B1%5D=payment'
		);
		expect( links.refunds ).toContain( 'type_is_in%5B0%5D=refund' );
		expect( links.refunds ).toContain( 'type_is_in%5B1%5D=payment_refund' );
		expect( links.disputes ).toContain( 'path=%2Fpayments%2Fdisputes' );
		expect( links.payouts ).toContain( 'path=%2Fpayments%2Fpayouts' );
		expect( links.charge_fees ).toContain( 'path=%2Fpayments%2Freports' );
		expect( links.charge_fees ).toContain( 'tab=fees' );

		for ( const key of [
			'total_charges_captured',
			'refunds',
			'disputes',
			'payouts',
			'charge_fees',
		] ) {
			expect( links[ key ] ).toContain(
				'date_between%5B0%5D=2026-05-01'
			);
			expect( links[ key ] ).toContain(
				'date_between%5B1%5D=2026-05-14'
			);
		}
	} );

	it( 'does not build Explore links for unsupported rows', () => {
		const unsupported = BALANCE_ROWS.filter(
			( row ) =>
				! [
					'total_charges_captured',
					'refunds',
					'disputes',
					'payouts',
					'charge_fees',
				].includes( row.key )
		);

		for ( const row of unsupported ) {
			expect( row.exploreLink ).toBeUndefined();
		}
	} );
} );
