/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { recordEvent } from 'tracks';

import { expectPresetButtonBefore } from '../../test-helpers';

const mockUseReportsFees = jest.fn();
const mockUseReportsFeesSummary = jest.fn();
const mockGetQuery = jest.fn( () => ( {} as Record< string, unknown > ) );
const mockUpdateQueryString = jest.fn();
const mockUpdateUserPreferences = jest.fn();
const mockSpeak = jest.fn();

jest.mock( 'wcpay/data/reports', () => ( {
	useReportsFees: ( q: unknown ) => mockUseReportsFees( q ),
	useReportsFeesSummary: ( q: unknown ) => mockUseReportsFeesSummary( q ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
	updateQueryString: ( args: Record< string, unknown >, path?: string ) =>
		mockUpdateQueryString( args, path ),
} ) );

jest.mock( '@woocommerce/data', () => ( {
	useUserPreferences: () => ( {
		updateUserPreferences: mockUpdateUserPreferences,
	} ),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	speak: ( message: string, politeness?: string ) =>
		mockSpeak( message, politeness ),
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const mockRecordEvent = recordEvent as jest.MockedFunction<
	typeof recordEvent
>;

jest.mock( 'multi-currency/interface/functions', () => ( {
	formatExplicitCurrency: ( amount: number, currency: string ) =>
		`${ currency.toUpperCase() } ${ amount }`,
} ) );

jest.mock( 'wcpay/components/details-link', () => ( {
	getDetailsURL: jest.fn( ( id: string ) => `/transaction/${ id }` ),
} ) );

jest.mock( 'wcpay/utils', () => ( {
	formatStringValue: ( value: string ) => value,
	getAdminUrl: ( args: Record< string, string | number > ) =>
		`/admin?${ new URLSearchParams(
			Object.entries( args ).map( ( [ key, value ] ) => [
				key,
				String( value ),
			] )
		).toString() }`,
} ) );

jest.mock( 'wcpay/utils/date-time', () => ( {
	formatDateTimeFromString: ( value: string ) => `formatted ${ value }`,
} ) );

jest.mock( '@woocommerce/components', () => ( {
	Link: ( {
		children,
		href,
	}: {
		children: React.ReactNode;
		href: string;
	} ) => <a href={ href }>{ children }</a>,
} ) );

/**
 * Internal dependencies
 */
import { FeesReport } from '../index';

const baseRow = {
	transaction_id: 'txn_1',
	date: '2026-04-15T10:00:00Z',
	type: 'charge',
	transaction_currency: 'usd',
	amount: 1000,
	deposit_currency: 'usd',
	fees: 30,
	order_id: 100,
	payment_method: { type: 'card' },
};

const emptyFeesSummary = { count: 0, sources: [], types: [] };
const defaultFeesSummary = {
	count: 1,
	sources: [ 'card' ],
	types: [ 'charge' ],
};
const filteredMarchQuery = {
	date_between: [ '2026-03-01', '2026-03-31' ],
	payment_method_type: 'card',
};
const fixedDateFilterNow = new Date( '2026-05-15T12:00:00.000Z' );
const previousMonthRange: [ string, string ] = [ '2026-04-01', '2026-04-30' ];
const previousYearRange: [ string, string ] = [ '2025-01-01', '2025-12-31' ];

type FeesRow = typeof baseRow;

type FeesState = {
	feesRows: FeesRow[];
	feesError: Record< string, unknown >;
	isLoading: boolean;
};

type FeesSummaryState = {
	feesSummary: {
		count?: number;
		sources?: string[];
		types?: string[];
	};
	isLoading: boolean;
};

const buildFeesRow = ( overrides: Partial< FeesRow > = {} ): FeesRow => ( {
	...baseRow,
	...overrides,
} );

const buildFeesState = (
	overrides: Partial< FeesState > = {}
): FeesState => ( {
	feesRows: [ buildFeesRow() ],
	feesError: {},
	isLoading: false,
	...overrides,
} );

const buildFeesSummaryState = (
	overrides: Partial< FeesSummaryState > = {}
): FeesSummaryState => ( {
	feesSummary: defaultFeesSummary,
	isLoading: false,
	...overrides,
} );

const mockFeesState = ( overrides: Partial< FeesState > = {} ) => {
	mockUseReportsFees.mockReturnValue( buildFeesState( overrides ) );
};

const mockFeesSummaryState = (
	overrides: Partial< FeesSummaryState > = {}
) => {
	mockUseReportsFeesSummary.mockReturnValue(
		buildFeesSummaryState( overrides )
	);
};

const mockFeesReportState = (
	feesOverrides: Partial< FeesState > = {},
	summaryOverrides: Partial< FeesSummaryState > = {}
) => {
	mockFeesState( feesOverrides );
	mockFeesSummaryState( summaryOverrides );
};

const mockEmptyFeesReportState = (
	isLoading = false,
	feesError: Record< string, unknown > = {}
) => {
	mockFeesReportState(
		{
			feesRows: [],
			feesError,
			isLoading,
		},
		{
			feesSummary: emptyFeesSummary,
			isLoading,
		}
	);
};

const expectRecordedTracksEvent = (
	eventName: string,
	properties: unknown
) => {
	expect( mockRecordEvent ).toHaveBeenCalledWith( eventName, properties );
};

const openFeesDateRangePopover = async () => {
	await userEvent.click(
		screen.getByRole( 'button', {
			name: 'Filter',
		} )
	);
	await userEvent.click(
		await screen.findByRole( 'button', {
			name: /Date between \(inc\):/,
		} )
	);
	return screen.findByRole( 'dialog' );
};

beforeEach( () => {
	mockUseReportsFees.mockReset();
	mockUseReportsFeesSummary.mockReset();
	mockGetQuery.mockReset().mockReturnValue( {} );
	mockUpdateQueryString.mockReset();
	mockUpdateUserPreferences.mockReset();
	mockSpeak.mockReset();
	mockRecordEvent.mockReset();

	( window as unknown as Record< string, unknown > ).wcpaySettings = {
		currentUserEmail: 'a@b.test',
	};
	( window as unknown as Record< string, unknown > ).wcSettings = {
		locale: { userLocale: 'en_US' },
	};

	mockFeesReportState();
} );

describe( 'FeesReport (DataViews)', () => {
	it( 'records load success when fees data finishes loading', () => {
		mockGetQuery.mockReturnValue( filteredMarchQuery );
		mockFeesReportState(
			{
				feesRows: [],
				isLoading: true,
			},
			{
				feesSummary: emptyFeesSummary,
				isLoading: true,
			}
		);

		const { rerender } = render( <FeesReport /> );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockFeesReportState(
			{
				feesRows: [],
			},
			{
				feesSummary: emptyFeesSummary,
			}
		);

		rerender( <FeesReport /> );

		expectRecordedTracksEvent( 'wcpay_reports_fees_load_success', {
			total_items: 0,
			has_filters: true,
			is_initial_empty: false,
			is_filtered_empty: true,
			range_days: 30,
		} );
	} );

	it( 'records load error when fees data resolves with an error', () => {
		mockGetQuery.mockReturnValue( filteredMarchQuery );
		mockFeesState( {
			feesRows: [],
		} );

		const { rerender } = render( <FeesReport /> );

		expect( mockRecordEvent ).not.toHaveBeenCalled();

		mockFeesState( {
			feesRows: [],
			feesError: { code: 'server_error' },
		} );

		rerender( <FeesReport /> );

		expectRecordedTracksEvent( 'wcpay_reports_fees_load_error', {
			has_filters: true,
			range_days: 30,
		} );
	} );

	it( 'records reload clicks from the Fees error state', async () => {
		const onReload = jest.fn();
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		mockFeesState( {
			feesRows: [],
			feesError: { code: 'server_error' },
		} );

		render( <FeesReport onReload={ onReload } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expectRecordedTracksEvent( 'wcpay_reports_fees_reload_click', {
			range_days: 30,
		} );
		expect( onReload ).toHaveBeenCalled();
	} );

	it( 'records load error when reloading from an existing Fees error fails again', () => {
		mockGetQuery.mockReturnValue( filteredMarchQuery );
		mockFeesState( {
			feesRows: [],
			feesError: { code: 'server_error' },
		} );

		const { rerender } = render( <FeesReport /> );

		expect( mockRecordEvent ).not.toHaveBeenCalledWith(
			'wcpay_reports_fees_load_error',
			expect.anything()
		);

		mockFeesState( {
			feesRows: [],
			feesError: { code: 'server_error' },
			isLoading: true,
		} );

		rerender( <FeesReport /> );

		mockFeesState( {
			feesRows: [],
			feesError: { code: 'server_error' },
		} );

		rerender( <FeesReport /> );

		expectRecordedTracksEvent( 'wcpay_reports_fees_load_error', {
			has_filters: true,
			range_days: 30,
		} );
	} );

	it.each( [
		{
			eventName: 'wcpay_reports_fees_load_success',
			nextError: {},
			expectedProperties: {
				has_filters: false,
				is_initial_empty: true,
				range_days: null,
			},
			startsLoading: true,
		},
		{
			eventName: 'wcpay_reports_fees_load_error',
			nextError: { code: 'server_error' },
			expectedProperties: {
				has_filters: false,
				range_days: null,
			},
			startsLoading: false,
		},
	] )(
		'records all-time $eventName telemetry with a null range',
		( { eventName, nextError, expectedProperties, startsLoading } ) => {
			mockEmptyFeesReportState( startsLoading );
			const { rerender } = render( <FeesReport /> );

			mockEmptyFeesReportState( false, nextError );

			rerender( <FeesReport /> );

			expectRecordedTracksEvent(
				eventName,
				expect.objectContaining( expectedProperties )
			);
		}
	);

	it( 'records all-time reload clicks with a null range', async () => {
		const onReload = jest.fn();
		mockEmptyFeesReportState( false, { code: 'server_error' } );

		render( <FeesReport onReload={ onReload } /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);

		expectRecordedTracksEvent( 'wcpay_reports_fees_reload_click', {
			range_days: null,
		} );
		expect( onReload ).toHaveBeenCalled();
	} );

	it( 'queries the data store with no date params when URL has no date filter', () => {
		render( <FeesReport /> );
		const call = mockUseReportsFees.mock.calls[ 0 ][ 0 ];
		expect( call.date_between ).toBeUndefined();
		expect( call.date_before ).toBeUndefined();
		expect( call.date_after ).toBeUndefined();
	} );

	it( 'queries date_between from URL when set', () => {
		mockGetQuery.mockReturnValue( {
			date_between: [ '2026-03-01', '2026-03-31' ],
		} );
		render( <FeesReport /> );
		expect( mockUseReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( {
				date_between: [ '2026-03-01', '2026-03-31' ],
			} )
		);
	} );

	it( 'queries date_before from URL when set', () => {
		mockGetQuery.mockReturnValue( {
			date_before: '2026-03-31',
		} );
		render( <FeesReport /> );
		expect( mockUseReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( { date_before: '2026-03-31' } )
		);
	} );

	it( 'renders native DataViews filtering controls without a default Date chip', () => {
		render( <FeesReport /> );

		expect(
			screen.queryByRole( 'button', { name: /^filter$/i } )
		).not.toBeInTheDocument();
		expect(
			screen.queryByRole( 'button', { name: /^date$/i } )
		).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /^add filter$/i } )
		).toBeInTheDocument();
	} );

	it( 'opens the native Date filter from Add filter', async () => {
		render( <FeesReport /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /^add filter$/i } )
		);
		await userEvent.click(
			await screen.findByRole( 'menuitem', { name: /^date$/i } )
		);

		expect(
			await screen.findByRole( 'button', { name: /^date$/i } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'dialog', {
				name: 'Custom date filter',
			} )
		).not.toBeInTheDocument();
	} );

	it.each( [
		{
			label: 'Previous month',
			expectedDateBetween: previousMonthRange,
		},
		{
			label: 'Previous year',
			expectedDateBetween: previousYearRange,
		},
	] )(
		'applies the $label preset from the between date filter as a complete calendar range',
		async ( { label, expectedDateBetween } ) => {
			jest.useFakeTimers();
			try {
				jest.setSystemTime( fixedDateFilterNow );
				mockGetQuery.mockReturnValue( {
					date_between: [ '2026-05-01', '2026-05-14' ],
				} );

				render( <FeesReport /> );
			} finally {
				jest.useRealTimers();
			}

			const filterPopover = await openFeesDateRangePopover();
			const presetButton = await within( filterPopover ).findByRole(
				'button',
				{
					name: label,
				}
			);

			expectPresetButtonBefore(
				filterPopover,
				'Previous month',
				'Last 7 days'
			);
			expectPresetButtonBefore(
				filterPopover,
				'Previous year',
				'Last 7 days'
			);

			jest.useFakeTimers();
			try {
				jest.setSystemTime( fixedDateFilterNow );
				act( () => {
					presetButton.click();
				} );
			} finally {
				jest.useRealTimers();
			}

			expect( mockUpdateQueryString ).toHaveBeenCalledWith(
				expect.objectContaining( {
					date_preset: undefined,
					date_between: expectedDateBetween,
					date_before: undefined,
					date_after: undefined,
				} ),
				'/payments/reports'
			);
		}
	);

	it.each( [
		{
			label: 'Previous month',
			value: previousMonthRange,
		},
		{
			label: 'Previous year',
			value: previousYearRange,
		},
	] )(
		'shows the $label preset as selected instead of Custom when the between date filter matches it',
		async ( { label, value } ) => {
			jest.useFakeTimers();
			try {
				jest.setSystemTime( fixedDateFilterNow );
				mockGetQuery.mockReturnValue( {
					date_between: value,
				} );

				render( <FeesReport /> );
			} finally {
				jest.useRealTimers();
			}

			const filterPopover = await openFeesDateRangePopover();
			const presetButton = await within( filterPopover ).findByRole(
				'button',
				{
					name: label,
				}
			);

			expect( presetButton ).toHaveAttribute( 'aria-pressed', 'true' );
			expect(
				within( filterPopover ).getByRole( 'button', {
					name: 'Custom',
				} )
			).toHaveAttribute( 'aria-pressed', 'false' );
			expect(
				within( filterPopover ).getByRole( 'button', {
					name: 'Custom',
				} )
			).not.toHaveAttribute( 'aria-disabled' );
		}
	);

	it( 'clears search, report filters, and the date filter from the report reset button', async () => {
		mockGetQuery.mockReturnValue( {
			search: [ 'txn_1' ],
			payment_method_type: 'card',
			type: [ 'charge' ],
			date_before: '2026-03-31',
		} );

		render( <FeesReport /> );
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Filter' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reset' } )
		);

		expect( mockUpdateQueryString ).toHaveBeenCalledWith(
			expect.objectContaining( {
				paged: '1',
				search: undefined,
				date_preset: undefined,
				date_between: undefined,
				date_before: undefined,
				date_after: undefined,
				payment_method_type: undefined,
				type: undefined,
			} ),
			'/payments/reports'
		);
	} );

	it( 'reads sort from URL into the query', () => {
		mockGetQuery.mockReturnValue( {
			orderby: 'amount',
			order: 'asc',
		} );
		render( <FeesReport /> );
		expect( mockUseReportsFees ).toHaveBeenCalledWith(
			expect.objectContaining( { orderby: 'amount', order: 'asc' } )
		);
	} );

	it( 'renders the row data through DataViews fields', async () => {
		render( <FeesReport /> );
		const items = await screen.findAllByText( 'txn_1' );
		expect( items.length ).toBeGreaterThan( 0 );
	} );

	it( 'renders the Figma error state with alert semantics and reload button when feesError is set', async () => {
		mockFeesState( {
			feesRows: [],
			feesError: { code: 'oops' },
		} );
		const onReload = jest.fn();
		const { container } = render( <FeesReport onReload={ onReload } /> );
		// `role="alert"` so AT users hear the error without focus management.
		expect( screen.getByRole( 'alert' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Fees report unavailable' )
		).toBeInTheDocument();
		expect(
			screen.getByText( /We couldn't load your fees data\./ )
		).toBeInTheDocument();
		expect(
			container.querySelector( '#wcpay-reports-fees-error-description' )
		).toHaveTextContent(
			/We couldn't load your fees data\.\s*Try again in a few minutes\./
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Reload report' } )
		);
		expect( onReload ).toHaveBeenCalled();
	} );

	it( 'renders the empty state when there are no rows and no active filters', () => {
		mockEmptyFeesReportState();

		render( <FeesReport /> );

		expect( screen.getByText( 'No fees yet' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'Fees will appear here once you start receiving payments.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders the filtered empty state when filters are active', async () => {
		mockGetQuery.mockReturnValue( {
			payment_method_type: 'card',
		} );
		mockEmptyFeesReportState();

		render( <FeesReport /> );

		expect( screen.queryByText( 'No fees yet' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'No fees to display' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Fees will appear here.' )
		).toBeInTheDocument();
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Filter' } )
		);
		expect( screen.getByRole( 'button', { name: 'Reset' } ) ).toBeEnabled();
	} );

	it( 'renders a loading status instead of DataViews No results while refreshing an empty filtered result', () => {
		mockGetQuery.mockReturnValue( {
			payment_method_type: 'card',
		} );
		mockEmptyFeesReportState();
		const { container, rerender } = render( <FeesReport /> );

		expect( screen.getByText( 'No fees to display' ) ).toBeInTheDocument();

		mockEmptyFeesReportState( true );
		rerender( <FeesReport /> );

		expect( screen.queryByText( 'No results' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'No fees to display' )
		).not.toBeInTheDocument();
		expect(
			container.querySelector(
				'.wcpay-reports-fees__loading-empty .components-spinner'
			)
		).toBeInTheDocument();
		expect( screen.queryByText( 'Loading fees' ) ).not.toBeInTheDocument();
		expect( screen.queryByRole( 'status' ) ).not.toBeInTheDocument();
	} );

	it( 'announces the loading status when the loading flag flips true', () => {
		mockFeesState();
		const { rerender } = render( <FeesReport /> );

		mockFeesState( {
			feesRows: [],
			isLoading: true,
		} );
		rerender( <FeesReport /> );

		expect( mockSpeak ).toHaveBeenCalledWith( 'Loading fees', 'polite' );
	} );

	it( 'does not render the export action in the Fees DataViews controls', () => {
		render( <FeesReport /> );
		expect(
			screen.queryByRole( 'button', { name: /export/i } )
		).not.toBeInTheDocument();
	} );

	it( 'announces the report-loaded status when the loading flag flips false (debounced)', () => {
		// The speak() announcement is debounced 500ms and de-duplicated to
		// keep rapid filter changes from spamming AT users — use fake timers
		// so we can flush the debounce deterministically.
		jest.useFakeTimers();
		try {
			mockFeesState( {
				feesRows: [],
				isLoading: true,
			} );
			const { rerender } = render( <FeesReport /> );
			mockSpeak.mockClear();

			mockFeesState();
			rerender( <FeesReport /> );

			// Pre-flush: debounce timer hasn't elapsed yet.
			expect( mockSpeak ).not.toHaveBeenCalled();

			act( () => {
				jest.advanceTimersByTime( 500 );
			} );

			expect( mockSpeak ).toHaveBeenCalledWith(
				expect.stringMatching( /loaded/i ),
				'polite'
			);
		} finally {
			jest.useRealTimers();
		}
	} );
} );
