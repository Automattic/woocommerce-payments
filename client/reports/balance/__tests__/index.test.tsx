/** @format */

import React from 'react';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { downloadCSVFile } from '@woocommerce/csv-export';

const mockCreateNotice = jest.fn();
const mockSpeak = jest.fn();
const mockUseReportsBalanceSummary = jest.fn();
const mockUseBalanceDateFilter = jest.fn();

jest.mock( '@wordpress/a11y', () => ( {
	speak: ( message: string, politeness?: string ) =>
		mockSpeak( message, politeness ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		createNotice: mockCreateNotice,
	} ),
} ) );

jest.mock( '@woocommerce/csv-export', () => {
	const actual = jest.requireActual( '@woocommerce/csv-export' );
	return {
		...actual,
		downloadCSVFile: jest.fn(),
	};
} );

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

const mockDownloadCSVFile = downloadCSVFile as jest.MockedFunction<
	typeof downloadCSVFile
>;

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

const getVisibleBalanceTable = () =>
	screen.getByRole( 'table', { name: 'Balance summary' } );

const expectBalanceText = ( text: string ) =>
	expect(
		within( getVisibleBalanceTable() ).getByText( text )
	).toBeInTheDocument();

beforeEach( () => {
	mockCreateNotice.mockReset();
	mockSpeak.mockReset();
	mockDownloadCSVFile.mockReset();
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

afterEach( () => {
	document.body.classList.remove( 'wcpay-reports-balance-print-context' );
	document.documentElement.classList.remove(
		'wcpay-reports-balance-print-context'
	);
} );

describe( 'BalanceReport', () => {
	it( 'requests Balance summary data for the active date-filter period', () => {
		render( <BalanceReport onReload={ jest.fn() } /> );

		expect( mockUseReportsBalanceSummary ).toHaveBeenCalledWith( period );
	} );

	it( 'renders the loading state with disabled export and print actions', () => {
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {},
			error: {},
			isLoading: true,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading report'
		);
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Export' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Print' } )
		).toBeDisabled();
	} );

	it( 'renders the error state with a reload action', async () => {
		const onReload = jest.fn();
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {},
			error: { code: 'server_error' },
			isLoading: false,
		} );

		render( <BalanceReport onReload={ onReload } /> );

		expect( screen.getByRole( 'alert' ) ).toContainElement(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		);
		expect(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Export' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Print' } )
		).toBeDisabled();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledWith( period );
	} );

	it( 'announces when Balance data finishes loading', () => {
		mockUseReportsBalanceSummary
			.mockReturnValueOnce( {
				summary: {},
				error: {},
				isLoading: true,
			} )
			.mockReturnValueOnce( {
				summary: balanceSummaryFixture,
				error: {},
				isLoading: false,
			} );

		const { rerender } = render( <BalanceReport onReload={ jest.fn() } /> );

		expect( mockSpeak ).not.toHaveBeenCalled();

		rerender( <BalanceReport onReload={ jest.fn() } /> );

		expect( mockSpeak ).toHaveBeenCalledWith(
			'Balance report loaded.',
			'polite'
		);
	} );

	it( 'scopes print styles while the Balance report is mounted', () => {
		const { unmount } = render( <BalanceReport onReload={ jest.fn() } /> );

		expect( document.body ).toHaveClass(
			'wcpay-reports-balance-print-context'
		);
		expect( document.documentElement ).toHaveClass(
			'wcpay-reports-balance-print-context'
		);

		unmount();

		expect( document.body ).not.toHaveClass(
			'wcpay-reports-balance-print-context'
		);
		expect( document.documentElement ).not.toHaveClass(
			'wcpay-reports-balance-print-context'
		);
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
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Export' } )
		).toBeDisabled();
		expect(
			screen.getByRole( 'button', { name: 'Print' } )
		).toBeDisabled();
	} );

	it( 'renders the canonical Balance summary rows', () => {
		render( <BalanceReport onReload={ jest.fn() } /> );

		expect(
			screen.getByRole( 'heading', { name: 'Balance summary' } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expectBalanceText( 'Starting balance - formatted 2024-03-01 UTC' );
		expectBalanceText( 'Ending balance - formatted 2024-03-31 UTC' );

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
			expectBalanceText( label );
		}

		expect(
			within( getVisibleBalanceTable() ).getByText( 'usd 162672' )
		).toBeInTheDocument();
		expect(
			within( getVisibleBalanceTable() ).getByText( 'usd -6064' )
		).toBeInTheDocument();
		expect(
			within( getVisibleBalanceTable() ).getByText( 'usd 1101608' )
		).toBeInTheDocument();
		expect(
			screen
				.getAllByText( '8' )
				.find( ( element ) =>
					element.classList.contains( 'wcpay-reports-balance__count' )
				)
		).toBeInTheDocument();
	} );

	it( 'downloads a machine-readable CSV for the selected UTC range', async () => {
		mockUseReportsBalanceSummary.mockReturnValue( {
			summary: {
				...balanceSummaryFixture,
				period,
			},
			error: {},
			isLoading: false,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
		expect( mockDownloadCSVFile ).toHaveBeenCalledWith(
			'wcpay-balance-2026-05-01_2026-05-14.csv',
			expect.any( String )
		);

		const csv = mockDownloadCSVFile.mock.calls[ 0 ][ 1 ] as string;
		expect( csv ).toMatch(
			/^row_key,label,amount,count,currency,period_start,period_end\n/
		);
		expect( csv ).toContain(
			'starting_balance,"Starting balance - formatted 2026-05-01 UTC",1000,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'total_charges_captured,"Total charges captured",162672,8,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).not.toContain( 'This Balance report summarizes' );
	} );

	it( 'surfaces a notice when CSV generation fails', async () => {
		mockDownloadCSVFile.mockImplementationOnce( () => {
			throw new Error( 'download failed' );
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockCreateNotice ).toHaveBeenCalledWith(
			'error',
			expect.stringContaining( 'problem generating' )
		);
	} );

	it( 'invokes the browser print preview from the Print button', async () => {
		const print = jest.fn();
		Object.defineProperty( window, 'print', {
			configurable: true,
			value: print,
		} );

		render( <BalanceReport onReload={ jest.fn() } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Print' } )
		);

		expect( print ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'renders print-only Balance report content outside the screen layout', () => {
		const { container } = render(
			<BalanceReport onReload={ jest.fn() } />
		);

		const printReport = container.querySelector(
			'.wcpay-reports-balance-print'
		) as HTMLElement;

		expect( printReport ).toBeInTheDocument();
		expect( printReport ).toHaveAttribute( 'aria-hidden', 'true' );
		expect(
			printReport.querySelector( 'img[alt="WooPayments"]' )
		).toBeInTheDocument();
		expect( printReport ).toHaveTextContent( 'WooPayments' );
		expect( printReport ).toHaveTextContent( 'Automattic Inc.' );
		expect( printReport ).toHaveTextContent( '60 29th Street #343' );
		expect( printReport ).toHaveTextContent(
			'San Francisco, CA, 94110, US'
		);
		expect( printReport ).not.toHaveTextContent(
			'This Balance report summarizes WooPayments balance activity for the selected UTC date range.'
		);
		expect( printReport ).not.toHaveTextContent( 'UTC date range:' );
		expect( printReport ).toHaveTextContent(
			'This report is provided for informational reconciliation purposes only.'
		);
		expect( printReport ).toHaveTextContent(
			'It is not an IRS form, tax statement, bank statement, legal document, or formal financial statement.'
		);
		const table = printReport.querySelector(
			'.wcpay-reports-balance-print__table'
		) as HTMLElement;
		expect( table ).toBeInTheDocument();
		expect(
			within( table ).getByRole( 'columnheader', {
				name: 'Balance summary',
				hidden: true,
			} )
		).toHaveAttribute( 'colspan', '2' );
		expect(
			within( table ).queryByRole( 'columnheader', {
				name: 'Balance row',
				hidden: true,
			} )
		).not.toBeInTheDocument();
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
		expectBalanceText( 'Starting balance - formatted 2024-03-01 UTC' );
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
