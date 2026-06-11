/** @format */

import React from 'react';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { recordEvent } from 'tracks';

const mockCreateNotice = jest.fn();
const mockSpeak = jest.fn();
const mockUseReportsBalanceSummary = jest.fn();
const mockUseBalanceDateFilter = jest.fn();
const mockSetBalanceDateFilterValue = jest.fn();
const mockDateFilterProps = jest.fn();
const mockAppliedDateFilterValue = {
	operator: 'between',
	value: [ '2026-04-01', '2026-04-30' ],
};
let consoleErrorSpy: jest.SpyInstance | undefined;

declare const global: {
	wcpaySettings?: typeof wcpaySettings;
};

let originalWcpaySettings: typeof wcpaySettings | undefined;

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

jest.mock( 'wcpay/data/reports', () => ( {
	useReportsBalanceSummary: ( period: unknown, currency?: string ) =>
		mockUseReportsBalanceSummary( period, currency ),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;

jest.mock( '../use-balance-date-filter', () => {
	const actual = jest.requireActual( '../use-balance-date-filter' );
	return {
		...actual,
		useBalanceDateFilter: ( now?: Date ) => mockUseBalanceDateFilter( now ),
	};
} );

jest.mock( 'wcpay/reports/date-filter', () => ( {
	__esModule: true,
	default: ( {
		label,
		now,
		onChange,
		onClear,
		value,
	}: {
		label?: string;
		now?: Date;
		onChange: ( next: unknown ) => void;
		onClear?: () => void;
		value?: unknown;
	} ) => {
		mockDateFilterProps( { now, value } );
		return (
			<>
				<button type="button">{ label ?? 'Date' }</button>
				<button
					type="button"
					onClick={ () => onChange( mockAppliedDateFilterValue ) }
				>
					Apply custom date
				</button>
				<button type="button" onClick={ () => onChange( undefined ) }>
					Clear incompatible date shape
				</button>
				<button type="button" onClick={ () => onClear?.() }>
					Clear Date filter
				</button>
			</>
		);
	},
} ) );

// Mirror the production helper's contract: `skipSymbol = true` returns the
// formatted amount followed by the ISO code (no `$`). `formatBalanceAmount`
// then strips the trailing code and prepends `±USD ` for the code-first layout
// the Figma uses.
jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: (
		amount: number,
		currency: string,
		skipSymbol?: boolean
	) =>
		skipSymbol ? `${ amount } ${ currency }` : `${ currency } ${ amount }`,
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
import { BalanceActions } from '../actions';
import { BalanceDateFilterNowContext } from '../context';

const mockDownloadCSVFile = downloadCSVFile as jest.MockedFunction<
	typeof downloadCSVFile
>;

const legalPrintBusinessDetails = [
	'WooPayments',
	'Automattic Inc.',
	'60 29th Street #343',
	'San Francisco, CA, 94110, US',
];

const period = {
	start: '2026-05-01T00:00:00.000Z',
	end: '2026-05-14T23:59:59.999Z',
};

const previousPeriod = {
	start: '2026-04-01T00:00:00.000Z',
	end: '2026-04-30T23:59:59.999Z',
};

type BalanceSummaryState = {
	summary: unknown;
	error: Record< string, unknown >;
	isLoading: boolean;
};

type BalanceDateFilterState = {
	value: unknown;
	period: typeof period | typeof previousPeriod | undefined;
	hasDateFilterValue: boolean;
	setValue: jest.Mock;
};

const buildBalanceSummaryState = (
	overrides: Partial< BalanceSummaryState > = {}
): BalanceSummaryState => ( {
	summary: balanceSummaryFixture,
	error: {},
	isLoading: false,
	...overrides,
} );

const mockBalanceSummaryState = (
	overrides: Partial< BalanceSummaryState > = {}
) => {
	mockUseReportsBalanceSummary.mockReturnValue(
		buildBalanceSummaryState( overrides )
	);
};

const buildBalanceDateFilterState = (
	overrides: Partial< BalanceDateFilterState > = {}
): BalanceDateFilterState => ( {
	value: undefined,
	period,
	hasDateFilterValue: true,
	setValue: mockSetBalanceDateFilterValue,
	...overrides,
} );

const mockBalanceDateFilterState = (
	overrides: Partial< BalanceDateFilterState > = {}
) => {
	mockUseBalanceDateFilter.mockReturnValue(
		buildBalanceDateFilterState( overrides )
	);
};

// In production, the Print/Export actions live in the page header so they
// stay visible across loading / error / empty states without re-rendering
// the body. Tests render them as siblings to make the same buttons
// queryable from a single test render — both components subscribe to the
// same date-filter + summary mocks, so behaviour matches production.
const balanceReportTree = (
	props: Parameters< typeof BalanceReport >[ 0 ] = {}
) => (
	<>
		<BalanceActions />
		<BalanceReport { ...props } />
	</>
);

const renderBalanceReport = (
	props: Parameters< typeof BalanceReport >[ 0 ] = {}
) => render( balanceReportTree( props ) );

const rerenderBalanceReport = (
	rerender: ReturnType< typeof render >[ 'rerender' ],
	props: Parameters< typeof BalanceReport >[ 0 ] = {}
) => rerender( balanceReportTree( props ) );

const renderBalanceReportWithDateFilterNow = (
	now: Date,
	props: Parameters< typeof BalanceReport >[ 0 ] = {}
) =>
	render(
		<BalanceDateFilterNowContext.Provider value={ now }>
			<BalanceReport { ...props } />
		</BalanceDateFilterNowContext.Provider>
	);

const setBalanceReportIdentitySettings = ( {
	businessName = global.wcpaySettings?.accountStatus?.businessName ?? '',
	accountId = global.wcpaySettings?.accountStatus?.accountId ?? '',
	storeName = global.wcpaySettings?.storeName ?? '',
}: Partial< {
	businessName: string;
	accountId: string;
	storeName: string;
} > ) => {
	global.wcpaySettings = {
		...( global.wcpaySettings ?? {} ),
		accountStatus: {
			...( global.wcpaySettings?.accountStatus ?? {} ),
			businessName,
			accountId,
		},
		storeName,
	} as typeof wcpaySettings;
};

const downloadBalanceCSV = async (): Promise< string > => {
	await userEvent.click( screen.getByRole( 'button', { name: 'Export' } ) );

	return mockDownloadCSVFile.mock.calls[ 0 ]?.[ 1 ] as string;
};

const getPrintReport = ( container: HTMLElement ): HTMLElement =>
	container.querySelector( '.wcpay-reports-balance-print' ) as HTMLElement;

const getPrintBusinessLines = ( printReport: HTMLElement ): string[] => {
	const businessBlock = within( printReport ).getByTestId(
		'balance-report-business'
	);

	return within( businessBlock )
		.getAllByTestId( 'balance-report-business-line' )
		.map( ( line ) => line.textContent ?? '' );
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

const expectActionButtonUnavailable = ( name: string ) => {
	const button = screen.getByRole( 'button', { name } );

	expect( button ).toHaveAttribute( 'aria-disabled', 'true' );
	expect( button ).not.toBeDisabled();
	act( () => {
		button.focus();
	} );
	expect( button ).toHaveFocus();
};

const expectRecordedTracksEvent = (
	eventName: string,
	properties: Record< string, unknown >,
	callNumber?: number
) => {
	const expectedProperties = expect.objectContaining( properties );

	if ( callNumber === undefined ) {
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			eventName,
			expectedProperties
		);
		return;
	}

	expect( mockRecordEvent ).toHaveBeenNthCalledWith(
		callNumber,
		eventName,
		expectedProperties
	);
};

beforeEach( () => {
	originalWcpaySettings = global.wcpaySettings;
	global.wcpaySettings = {
		...( global.wcpaySettings ?? {} ),
		accountDefaultCurrency: 'USD',
		accountStatus: {
			...( global.wcpaySettings?.accountStatus ?? {} ),
		},
		storeName: 'Aperture Store',
	} as typeof wcpaySettings;
	setBalanceReportIdentitySettings( {
		businessName: 'Aperture Science LLC',
		accountId: 'acct_wcpay_123',
		storeName: 'Aperture Store',
	} );
	mockCreateNotice.mockReset();
	mockSpeak.mockReset();
	mockDownloadCSVFile.mockReset();
	mockUseReportsBalanceSummary.mockReset();
	mockSetBalanceDateFilterValue.mockReset();
	mockRecordEvent.mockReset();
	mockDateFilterProps.mockReset();
	consoleErrorSpy = undefined;
	mockBalanceDateFilterState();
	mockBalanceSummaryState();
} );

afterEach( () => {
	document.body.classList.remove( 'wcpay-reports-balance-print-context' );
	document.documentElement.classList.remove(
		'wcpay-reports-balance-print-context'
	);
	jest.useRealTimers();
	jest.restoreAllMocks();
	if ( originalWcpaySettings === undefined ) {
		delete global.wcpaySettings;
	} else {
		global.wcpaySettings = originalWcpaySettings;
	}
} );

describe( 'BalanceReport', () => {
	it( 'requests Balance summary data for the active date-filter period', () => {
		renderBalanceReport( { onReload: jest.fn() } );

		expect( mockUseReportsBalanceSummary ).toHaveBeenCalledWith(
			period,
			expect.any( String )
		);
	} );

	it( 'clears the active Date filter from the toolbar Reset button', async () => {
		const { container } = renderBalanceReport( { onReload: jest.fn() } );
		const toolbar = container.querySelector(
			'.wcpay-reports-balance__toolbar'
		) as HTMLElement;

		expect(
			within( toolbar ).getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		const resetButton = within( toolbar ).getByRole( 'button', {
			name: 'Reset',
		} );

		await userEvent.click( resetButton );

		expect( mockSetBalanceDateFilterValue ).toHaveBeenCalledWith(
			undefined
		);
	} );

	it( 'moves focus from the Date filter to the error heading when a refresh fails', () => {
		// Use mockReturnValue (not Once) so both BalanceActions and
		// BalanceReport — which each call the hook in production — see the
		// same state on every render.
		mockBalanceSummaryState();

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );
		screen.getByRole( 'button', { name: 'Date' } ).focus();

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toHaveFocus();
	} );

	it( 'does not move focus to the error heading when focus is outside the report', () => {
		mockBalanceSummaryState();

		const { rerender } = render(
			<>
				<button type="button">Outside report</button>
				<BalanceReport onReload={ jest.fn() } />
			</>
		);
		screen.getByRole( 'button', { name: 'Outside report' } ).focus();

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerender(
			<>
				<button type="button">Outside report</button>
				<BalanceReport onReload={ jest.fn() } />
			</>
		);

		expect(
			screen.getByRole( 'button', { name: 'Outside report' } )
		).toHaveFocus();
	} );

	it( 'renders the loading state with disabled export and print actions', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		renderBalanceReport( { onReload: jest.fn() } );

		expect( screen.getByRole( 'status' ) ).toHaveTextContent(
			'Loading balance report'
		);
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expectActionButtonUnavailable( 'Export' );
		expectActionButtonUnavailable( 'Print' );
	} );

	it( 'renders the error state with a reload action', async () => {
		const onReload = jest.fn();
		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		renderBalanceReport( { onReload } );

		const alert = screen.getByRole( 'alert' );

		expect( alert ).toContainElement(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		);
		expect( alert ).toHaveTextContent(
			/We couldn't load your balance data\.\s*Try again in a few minutes\./
		);
		expect(
			screen.getByRole( 'button', { name: 'Date' } )
		).toBeInTheDocument();
		expectActionButtonUnavailable( 'Export' );
		expectActionButtonUnavailable( 'Print' );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledWith( period );
	} );

	it( 'treats Balance summaries missing required metadata as unavailable', async () => {
		const onReload = jest.fn();
		mockBalanceSummaryState( {
			summary: {
				...balanceSummaryFixture,
				currency: undefined,
			},
		} );

		renderBalanceReport( { onReload } );

		expect(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toBeInTheDocument();
		expectActionButtonUnavailable( 'Export' );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);
		expect( mockDownloadCSVFile ).not.toHaveBeenCalled();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);
		expect( onReload ).toHaveBeenCalledWith( period );
	} );

	it( 'moves focus to the loading heading after Reload starts a refresh', async () => {
		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );
		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );
		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect(
			screen.getByRole( 'heading', { name: 'Loading balance report' } )
		).toHaveFocus();
	} );

	it( 'moves focus to the error heading when loading fails', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );
		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );
		screen.getByRole( 'button', { name: 'Date' } ).focus();

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );
		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect(
			screen.getByRole( 'heading', { name: 'Balance unavailable' } )
		).toHaveFocus();
	} );

	it( 'announces when Balance data finishes loading', () => {
		jest.useFakeTimers();
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockSpeak ).not.toHaveBeenCalled();

		mockBalanceSummaryState();

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockSpeak ).not.toHaveBeenCalled();

		act( () => {
			jest.advanceTimersByTime( 500 );
		} );

		expect( mockSpeak ).toHaveBeenCalledWith(
			'Balance report loaded.',
			undefined
		);
	} );

	it( 'does not announce a stale Balance load when the Date filter is cleared while loading', () => {
		jest.useFakeTimers();
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		mockBalanceDateFilterState( {
			hasDateFilterValue: false,
		} );
		mockBalanceSummaryState( {
			summary: {},
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		act( () => {
			jest.advanceTimersByTime( 500 );
		} );

		expect( mockSpeak ).not.toHaveBeenCalled();
	} );

	it( 'does not duplicate the alert announcement when Balance data fails to load', () => {
		jest.useFakeTimers();
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockSpeak ).not.toHaveBeenCalled();

		act( () => {
			jest.advanceTimersByTime( 500 );
		} );

		expect( mockSpeak ).not.toHaveBeenCalled();
	} );

	it( 'scopes print styles while Balance actions are available', () => {
		const { unmount } = renderBalanceReport( { onReload: jest.fn() } );

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

	it( 'does not scope print styles while Balance actions are unavailable', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		renderBalanceReport( { onReload: jest.fn() } );

		expect( document.body ).not.toHaveClass(
			'wcpay-reports-balance-print-context'
		);
		expect( document.documentElement ).not.toHaveClass(
			'wcpay-reports-balance-print-context'
		);
	} );

	it( 'renders the empty state when every row is zero', () => {
		mockBalanceSummaryState( {
			summary: zeroSummary,
		} );

		renderBalanceReport( { onReload: jest.fn() } );

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
		expectActionButtonUnavailable( 'Export' );
		expectActionButtonUnavailable( 'Print' );
	} );

	it( 'renders the empty state without requesting data when the Date filter is inactive', () => {
		mockBalanceDateFilterState( {
			hasDateFilterValue: false,
			setValue: jest.fn(),
		} );

		renderBalanceReport( { onReload: jest.fn() } );

		expect( mockUseReportsBalanceSummary ).toHaveBeenCalledWith(
			undefined,
			expect.any( String )
		);
		expect(
			screen.getByRole( 'heading', { name: 'No balance activity' } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'table', { name: 'Balance summary' } )
		).not.toBeInTheDocument();
		expectActionButtonUnavailable( 'Export' );
		expectActionButtonUnavailable( 'Print' );
	} );

	it( 'renders the canonical Balance summary rows', () => {
		renderBalanceReport( { onReload: jest.fn() } );

		// "Balance summary" is the table's <caption>, not a separate heading
		// — assert it via the table's accessible name + visible caption text.
		expect( getVisibleBalanceTable() ).toBeInTheDocument();
		expect(
			within( getVisibleBalanceTable() ).getByText( 'Balance summary' )
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
			'Reader costs',
			'Dispute fees',
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

		// formatBalanceAmount renders sign + code + space + amount.
		expect(
			within( getVisibleBalanceTable() ).getByText( '+USD 162672' )
		).toBeInTheDocument();
		expect(
			within( getVisibleBalanceTable() ).getByText( '-USD 6064' )
		).toBeInTheDocument();
		// Payouts is forced negative even when the raw value is positive.
		expect(
			within( getVisibleBalanceTable() ).getByText( '-USD 1102608' )
		).toBeInTheDocument();
		const chargesRow = screen.getByRole( 'row', {
			name: /Total charges captured/,
		} );
		expect(
			screen.getByRole( 'row', {
				name: /Total charges captured 8 items/,
			} )
		).toBe( chargesRow );
		expect( within( chargesRow ).getByText( '8' ) ).toHaveAttribute(
			'aria-hidden',
			'true'
		);
		expect(
			within( chargesRow ).queryByRole( 'generic', { name: '8' } )
		).not.toBeInTheDocument();
		expect(
			within( chargesRow ).getByText( '8 items' )
		).toBeInTheDocument();
	} );

	it( 'downloads a machine-readable CSV for the selected UTC range', async () => {
		mockBalanceSummaryState( {
			summary: {
				...balanceSummaryFixture,
				period,
			},
		} );

		renderBalanceReport( { onReload: jest.fn() } );

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
			/^business_name,woopayments_account_id,row_key,label,amount,count,currency,period_start,period_end\n/
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,starting_balance,"Starting balance - formatted 2026-05-01 UTC",1000,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,total_charges_captured,"Total charges captured",162672,8,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,fees,Fees,-6064,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,charge_fees,"Charge fees",-5958,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,dispute_fees,"Dispute fees",-1500,,usd,2026-05-01,2026-05-14'
		);
		// `fee_refunds` is positive in the fixture — pins the sign convention
		// for the one sub-row that diverges from the negative fee siblings.
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,fee_refunds,"Fee refunds",1644,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,payout_fees,"Payout fees",-100,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,reader_fees,"Reader costs",-150,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,refunds,Refunds,-21500,3,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).toContain(
			'"Aperture Science LLC",acct_wcpay_123,ending_balance,"Ending balance - formatted 2026-05-14 UTC",0,,usd,2026-05-01,2026-05-14'
		);
		expect( csv ).not.toContain(
			'This report is provided for informational reconciliation purposes only.'
		);
	} );

	it( 'surfaces a notice when CSV generation fails', async () => {
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );
		const error = new Error( 'download failed' );
		mockDownloadCSVFile.mockImplementationOnce( () => {
			throw error;
		} );

		renderBalanceReport( { onReload: jest.fn() } );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		expect( mockCreateNotice ).toHaveBeenCalledWith(
			'error',
			expect.stringContaining( 'problem generating' )
		);
		expect( consoleErrorSpy ).toHaveBeenCalledWith(
			'Balance CSV export failed:',
			error
		);
	} );

	it( 'invokes the browser print preview from the Print button', async () => {
		const print = jest.fn();
		Object.defineProperty( window, 'print', {
			configurable: true,
			value: print,
		} );

		renderBalanceReport( { onReload: jest.fn() } );

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
		expect( printReport ).toHaveTextContent( 'Aperture Science LLC' );
		expect( printReport ).toHaveTextContent( 'acct_wcpay_123' );
		expect( printReport ).not.toHaveTextContent(
			'WooPayments account ID:'
		);
		expect( printReport ).toHaveTextContent( 'Automattic Inc.' );
		expect( printReport ).toHaveTextContent( '60 29th Street #343' );
		expect( printReport ).toHaveTextContent(
			'San Francisco, CA, 94110, US'
		);
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

	describe( 'getBalanceReportIdentity fallback behavior', () => {
		it( 'uses the store name in CSV and print output when the account business name is empty', async () => {
			setBalanceReportIdentitySettings( {
				businessName: '',
				storeName: 'Aperture Store',
			} );
			const { container } = renderBalanceReport( {
				onReload: jest.fn(),
			} );

			const csv = await downloadBalanceCSV();

			expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
			expect( csv ).toContain(
				'"Aperture Store",acct_wcpay_123,starting_balance,"Starting balance - formatted 2024-03-01 UTC",1000,,usd,2024-03-01,2024-03-31'
			);
			expect(
				getPrintBusinessLines( getPrintReport( container ) )
			).toEqual( [
				'Aperture Store',
				'acct_wcpay_123',
				...legalPrintBusinessDetails,
			] );
		} );

		it( 'leaves CSV identity fields empty and suppresses optional print lines when identity data is unavailable', async () => {
			setBalanceReportIdentitySettings( {
				businessName: '',
				accountId: '',
				storeName: '',
			} );
			const { container } = renderBalanceReport( {
				onReload: jest.fn(),
			} );

			const csv = await downloadBalanceCSV();

			expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
			expect( csv ).toContain(
				'\n,,starting_balance,"Starting balance - formatted 2024-03-01 UTC",1000,,usd,2024-03-01,2024-03-31'
			);
			expect(
				getPrintBusinessLines( getPrintReport( container ) )
			).toEqual( legalPrintBusinessDetails );
		} );

		it( 'leaves the CSV account column empty and suppresses the print account ID line when account ID is empty', async () => {
			setBalanceReportIdentitySettings( {
				accountId: '',
			} );
			const { container } = renderBalanceReport( {
				onReload: jest.fn(),
			} );

			const csv = await downloadBalanceCSV();

			expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
			expect( csv ).toContain(
				'"Aperture Science LLC",,starting_balance,"Starting balance - formatted 2024-03-01 UTC",1000,,usd,2024-03-01,2024-03-31'
			);
			expect(
				getPrintBusinessLines( getPrintReport( container ) )
			).toEqual( [
				'Aperture Science LLC',
				...legalPrintBusinessDetails,
			] );
		} );

		it( 'quotes comma-containing business names in CSV output', async () => {
			setBalanceReportIdentitySettings( {
				businessName: 'Smith, Jones & Associates',
			} );
			renderBalanceReport( { onReload: jest.fn() } );

			const csv = await downloadBalanceCSV();

			expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
			expect( csv ).toContain(
				'"Smith, Jones & Associates",acct_wcpay_123,starting_balance,"Starting balance - formatted 2024-03-01 UTC",1000,,usd,2024-03-01,2024-03-31'
			);
		} );
	} );

	it( 'hides optional rows when their amount and count are zero', () => {
		mockBalanceSummaryState( {
			summary: {
				...balanceSummaryFixture,
				network_costs: { amount: 0, count: 0 },
				other_adjustments: { amount: 0, count: 0 },
			},
		} );

		renderBalanceReport( { onReload: jest.fn() } );

		expect( screen.queryByText( 'Network costs' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Other adjustments' )
		).not.toBeInTheDocument();
		expectBalanceText( 'Starting balance - formatted 2024-03-01 UTC' );
	} );
} );

describe( 'BalanceReport Tracks', () => {
	it( 'records load success when Balance summary data finishes loading', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockBalanceSummaryState();

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expectRecordedTracksEvent( 'wcpay_reports_balance_load_success', {
			currency: 'usd',
			has_activity: true,
			visible_row_count: expect.any( Number ),
			range_days: 31,
		} );
	} );

	it( 'does not record load success when the Date filter is cleared while Balance data is loading', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockBalanceDateFilterState( {
			hasDateFilterValue: false,
		} );
		mockBalanceSummaryState( {
			summary: {},
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );

	it( 'does not record load success when a different Balance period is active after loading', () => {
		mockBalanceDateFilterState( {
			period: previousPeriod,
		} );
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockBalanceDateFilterState();
		mockBalanceSummaryState();

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();
	} );

	it( 'records load error when Balance summary data resolves with a store error', () => {
		mockBalanceSummaryState( {
			summary: {},
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expectRecordedTracksEvent( 'wcpay_reports_balance_load_error', {
			error_type: 'store',
			range_days: 14,
		} );
	} );

	it( 'records reload clicks from the Balance summary error state', async () => {
		const onReload = jest.fn();
		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		renderBalanceReport( { onReload } );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expectRecordedTracksEvent( 'wcpay_reports_balance_reload_click', {
			range_days: 14,
		} );
		expect( onReload ).toHaveBeenCalledWith( period );
	} );

	it( 'records load error when a reload finishes in the cached error state', async () => {
		const onReload = jest.fn();
		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		const { rerender } = renderBalanceReport( { onReload } );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expect( onReload ).toHaveBeenCalledWith( period );
		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expectRecordedTracksEvent(
			'wcpay_reports_balance_reload_click',
			{
				range_days: 14,
			},
			1
		);

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
			isLoading: true,
		} );

		rerenderBalanceReport( rerender, { onReload } );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerenderBalanceReport( rerender, { onReload } );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 );
		expectRecordedTracksEvent(
			'wcpay_reports_balance_load_error',
			{
				error_type: 'store',
				range_days: 14,
			},
			2
		);
	} );

	it( 'does not record load error while cached errors are still loading', () => {
		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
			isLoading: true,
		} );

		const { rerender } = renderBalanceReport( { onReload: jest.fn() } );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockBalanceSummaryState( {
			summary: {},
			error: { code: 'server_error' },
		} );

		rerenderBalanceReport( rerender, { onReload: jest.fn() } );

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expectRecordedTracksEvent( 'wcpay_reports_balance_load_error', {
			error_type: 'store',
			range_days: 14,
		} );
	} );

	it.each( [
		{
			name: 'initial date filter applies',
			hasDateFilterValue: false,
			isInitialApply: true,
		},
		{
			name: 'subsequent date filter applies',
			hasDateFilterValue: true,
			isInitialApply: false,
		},
	] )(
		'records $name with the stable Balance date-filter reference date',
		async ( { hasDateFilterValue, isInitialApply } ) => {
			jest.useFakeTimers();
			jest.setSystemTime( new Date( '2026-06-15T12:00:00.000Z' ) );
			const stableNow = new Date( '2026-05-15T12:00:00.000Z' );
			mockBalanceDateFilterState( {
				hasDateFilterValue,
			} );

			renderBalanceReportWithDateFilterNow( stableNow, {
				onReload: jest.fn(),
			} );

			await userEvent.click(
				screen.getByRole( 'button', { name: 'Apply custom date' } )
			);

			expect( mockUseBalanceDateFilter ).toHaveBeenCalledWith(
				stableNow
			);
			expect( mockDateFilterProps ).toHaveBeenCalledWith(
				expect.objectContaining( {
					now: stableNow,
				} )
			);
			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
			expectRecordedTracksEvent(
				'wcpay_reports_balance_date_filter_change',
				{
					preset: 'last_month',
					range_days: 30,
					is_initial_apply: isInitialApply,
				}
			);
			expect( mockSetBalanceDateFilterValue ).toHaveBeenCalledWith(
				mockAppliedDateFilterValue
			);
		}
	);

	it.each( [
		{
			source: 'toolbar Reset button',
			clickReset: async () => {
				const toolbar = document.querySelector(
					'.wcpay-reports-balance__toolbar'
				) as HTMLElement;
				await userEvent.click(
					within( toolbar ).getByRole( 'button', { name: 'Reset' } )
				);
			},
		},
		{
			source: 'DateFilter chip clear button',
			clickReset: async () => {
				await userEvent.click(
					screen.getByRole( 'button', {
						name: 'Clear Date filter',
					} )
				);
			},
		},
	] )(
		'records reset date filter changes from the $source',
		async ( { clickReset } ) => {
			renderBalanceReport( { onReload: jest.fn() } );

			await clickReset();
			expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
			expectRecordedTracksEvent(
				'wcpay_reports_balance_date_filter_change',
				{
					preset: 'reset',
					range_days: null,
					is_initial_apply: false,
				}
			);
			expect( mockSetBalanceDateFilterValue ).toHaveBeenCalledWith(
				undefined
			);
		}
	);

	it( 'does not record reset telemetry when DateFilter clears an incompatible date shape', async () => {
		renderBalanceReport( { onReload: jest.fn() } );

		await userEvent.click(
			screen.getByRole( 'button', {
				name: 'Clear incompatible date shape',
			} )
		);

		expect( mockRecordEvent ).not.toHaveBeenCalled();
		expect( mockSetBalanceDateFilterValue ).toHaveBeenCalledWith(
			undefined
		);
	} );
} );
