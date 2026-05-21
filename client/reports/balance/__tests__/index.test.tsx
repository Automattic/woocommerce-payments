/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const mockUseReportsBalanceSummary = jest.fn();
const mockUseBalanceDateFilter = jest.fn();

jest.mock( 'wcpay/data', () => ( {
	useReportsBalanceSummary: ( period: unknown ) =>
		mockUseReportsBalanceSummary( period ),
} ) );

jest.mock( '../use-balance-date-filter', () => ( {
	useBalanceDateFilter: () => mockUseBalanceDateFilter(),
} ) );

jest.mock( 'wcpay/reports/date-filter', () => ( {
	__esModule: true,
	default: ( {
		label,
	}: {
		label?: string;
		value?: unknown;
		onChange: ( next: unknown ) => void;
	} ) => <button type="button">{ label ?? 'Date' }</button>,
} ) );

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: ( amount: number, currency: string ) =>
		`${ currency } ${ amount }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) =>
		`formatted ${ value.slice( 0, 10 ) }`,
} ) );

jest.mock( 'wcpay/utils', () => ( {
	getAdminUrl: ( args: Record< string, unknown > ) => {
		const params = new URLSearchParams();
		Object.entries( args ).forEach( ( [ key, value ] ) => {
			if ( Array.isArray( value ) ) {
				value.forEach( ( item, index ) =>
					params.append( `${ key }[${ index }]`, String( item ) )
				);
				return;
			}
			if ( value !== undefined ) {
				params.append( key, String( value ) );
			}
		} );
		return `admin.php?${ params.toString() }`;
	},
} ) );

/**
 * Internal dependencies
 */
import balanceSummaryFixture from 'wcpay/data/reports/fixtures/balance-summary';
import { BalanceReport } from '../index';

const period = {
	start: '2026-05-01T00:00:00.000Z',
	end: '2026-05-14T23:59:59.999Z',
};

const zeroSummary = {
	currency: 'usd',
	period,
	starting_balance: { amount: 0 },
	total_charges_captured: { amount: 0, count: 0 },
	fees: { amount: 0 },
	charge_fees: { amount: 0 },
	payout_fees: { amount: 0 },
	reader_fees: { amount: 0 },
	dispute_fees: { amount: 0 },
	fee_refunds: { amount: 0 },
	refunds: { amount: 0, count: 0 },
	refund_failure: { amount: 0, count: 0 },
	disputes: { amount: 0, count: 0 },
	financing_payout: { amount: 0, count: 0 },
	financing_paydown: { amount: 0, count: 0 },
	network_costs: { amount: 0, count: 0 },
	other_adjustments: { amount: 0, count: 0 },
	net_balance_change_in_the_period: { amount: 0 },
	payouts: { amount: 0, count: 0 },
	ending_balance: { amount: 0 },
};

beforeEach( () => {
	mockUseBalanceDateFilter.mockReturnValue( {
		value: undefined,
		period,
		setValue: jest.fn(),
	} );
	mockUseReportsBalanceSummary.mockReturnValue( {
		summary: balanceSummaryFixture,
		error: {},
		isLoading: false,
	} );
} );

describe( 'BalanceReport', () => {
	it( 'requests Balance summary data for the active date-filter period', () => {
		render( <BalanceReport onReload={ jest.fn() } /> );

		expect( mockUseReportsBalanceSummary ).toHaveBeenCalledWith( period );
	} );

	it( 'renders the loading state', () => {
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {},
			error: {},
			isLoading: true,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading report'
		);
	} );

	it( 'renders the error state with a reload action', async () => {
		const onReload = jest.fn();
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {},
			error: { code: 'server_error' },
			isLoading: false,
		} );

		render( <BalanceReport onReload={ onReload } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toBeInTheDocument();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledWith( period );
	} );

	it( 'renders the empty state when every row is zero', () => {
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: zeroSummary,
			error: {},
			isLoading: false,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		expect(
			screen.getByRole( 'heading', { name: 'No balance activity' } )
		).toBeInTheDocument();
		expect(
			screen.getByText(
				"Your Balance summary will appear here once there's enough data to display."
			)
		).toBeInTheDocument();
	} );

	it( 'renders the canonical Balance summary rows', () => {
		render( <BalanceReport onReload={ jest.fn() } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Balance summary' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Starting balance - formatted 2024-03-01 UTC' )
		).toBeInTheDocument();
		expect(
			screen.getByText( 'Ending balance - formatted 2024-03-31 UTC' )
		).toBeInTheDocument();

		for ( const label of [
			'Total charges captured',
			'Fees',
			'Charge fees',
			'Payout fees',
			'Reader fees',
			'Disputes fees',
			'Fee refunds',
			'Refunds',
			'Refund failures',
			'Disputes',
			'Financing payout',
			'Financing paydown',
			'Network costs',
			'Other adjustments',
			'Net balance change in the period',
			'Payouts',
		] ) {
			expect( screen.getByText( label ) ).toBeInTheDocument();
		}

		expect( screen.getByText( 'usd 162672' ) ).toBeInTheDocument();
		expect( screen.getByText( 'usd -6064' ) ).toBeInTheDocument();
		expect( screen.getByText( 'usd 1101608' ) ).toBeInTheDocument();
		expect( screen.getAllByText( '8' )[ 0 ] ).toHaveClass(
			'wcpay-reports-balance__count'
		);
	} );

	it( 'hides optional rows when their amount and count are zero', () => {
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {
				...balanceSummaryFixture,
				network_costs: { amount: 0, count: 0 },
				other_adjustments: { amount: 0, count: 0 },
			},
			error: {},
			isLoading: false,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		expect( screen.queryByText( 'Network costs' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Other adjustments' )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( 'Starting balance - formatted 2024-03-01 UTC' )
		).toBeInTheDocument();
	} );

	it( 'renders Explore links for supported rows', () => {
		render( <BalanceReport onReload={ jest.fn() } /> );

		const chargesRow = screen.getByRole( 'row', {
			name: /Total charges captured/,
		} );
		const chargesLink = within( chargesRow ).getByRole( 'link', {
			name: 'Explore ->',
		} );

		expect( chargesLink ).toHaveAttribute(
			'href',
			expect.stringContaining( 'type_is_in%5B0%5D=charge' )
		);
		expect( chargesLink ).toHaveAttribute(
			'href',
			expect.stringContaining( 'date_between%5B0%5D=2024-03-01' )
		);
	} );
} );
