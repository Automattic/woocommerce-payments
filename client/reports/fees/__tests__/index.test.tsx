/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

const mockUseReportsFees = jest.fn();
const mockUseReportsFeesSummary = jest.fn();
const mockGetQuery = jest.fn( () => ( {} as Record< string, unknown > ) );
const mockUpdateQueryString = jest.fn();
const mockUpdateUserPreferences = jest.fn();
const mockRecordEvent = jest.fn();
const mockSpeak = jest.fn();
const i18nTranslateFunction = '__';

jest.mock( 'wcpay/data', () => ( {
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
	speak: ( message: string ) => mockSpeak( message ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	...jest.requireActual( '@wordpress/i18n' ),
	[ i18nTranslateFunction ]: ( text: string ) =>
		text === 'Date' ? 'Fecha' : text,
} ) );

jest.mock( 'tracks', () => ( {
	recordEvent: ( event: string, props: unknown ) =>
		mockRecordEvent( event, props ),
} ) );

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

beforeEach( () => {
	mockUseReportsFees.mockReset();
	mockUseReportsFeesSummary.mockReset();
	mockGetQuery.mockReset().mockReturnValue( {} );
	mockUpdateQueryString.mockReset();
	mockUpdateUserPreferences.mockReset();
	mockRecordEvent.mockReset();
	mockSpeak.mockReset();

	( window as unknown as Record< string, unknown > ).wcpaySettings = {
		currentUserEmail: 'a@b.test',
	};
	( window as unknown as Record< string, unknown > ).wcSettings = {
		locale: { userLocale: 'en_US' },
	};

	mockUseReportsFees.mockReturnValue( {
		feesRows: [ baseRow ],
		feesError: {},
		isLoading: false,
	} );
	mockUseReportsFeesSummary.mockReturnValue( {
		feesSummary: {
			count: 1,
			sources: [ 'card' ],
			types: [ 'charge' ],
		},
		isLoading: false,
	} );
} );

describe( 'FeesReport (DataViews)', () => {
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

	it( 'renders the Date filter chip', () => {
		render( <FeesReport /> );
		expect(
			screen.getByRole( 'button', { name: /^fecha$/i } )
		).toBeInTheDocument();
	} );

	it( 'opens the custom date popover directly from the Date filter chip', async () => {
		render( <FeesReport /> );

		fireEvent.click( screen.getByRole( 'button', { name: /^fecha$/i } ) );

		expect(
			await screen.findByRole( 'dialog', {
				name: 'Custom date filter',
			} )
		).toBeInTheDocument();
		await waitFor( () =>
			expect(
				screen.queryByRole( 'option', { name: 'Custom date…' } )
			).not.toBeInTheDocument()
		);
	} );

	it( 'toggles the custom date popover closed from the Date filter chip', async () => {
		render( <FeesReport /> );

		const dateFilterChip = screen.getByRole( 'button', {
			name: /^fecha$/i,
		} );
		fireEvent.pointerDown( dateFilterChip, { button: 0 } );
		expect(
			await screen.findByRole( 'dialog', {
				name: 'Custom date filter',
			} )
		).toBeInTheDocument();

		fireEvent.click( dateFilterChip );
		expect(
			screen.getByRole( 'dialog', {
				name: 'Custom date filter',
			} )
		).toBeInTheDocument();

		fireEvent.pointerDown( dateFilterChip, { button: 0 } );

		await waitFor( () =>
			expect(
				screen.queryByRole( 'dialog', {
					name: 'Custom date filter',
				} )
			).not.toBeInTheDocument()
		);

		fireEvent.click( dateFilterChip );
		expect(
			screen.queryByRole( 'dialog', {
				name: 'Custom date filter',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'does not expose the internal date-filter anchor text', () => {
		render( <FeesReport /> );
		expect(
			screen.queryByText( '_wcpay_date_filter_anchor' )
		).not.toBeInTheDocument();
	} );

	it( 'clears search, report filters, and the date filter from the report reset button', async () => {
		mockGetQuery.mockReturnValue( {
			search: [ 'txn_1' ],
			payment_method_type: 'card',
			type: [ 'charge' ],
			date_before: '2026-03-31',
		} );

		render( <FeesReport /> );
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
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: { code: 'oops' },
			isLoading: false,
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
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 0, sources: [], types: [] },
			isLoading: false,
		} );
		render( <FeesReport /> );
		expect( screen.getByText( 'No fees yet' ) ).toBeInTheDocument();
		expect(
			screen.getByText(
				'Fees will appear here once you start receiving payments.'
			)
		).toBeInTheDocument();
	} );

	it( 'renders the filtered empty state when filters are active', () => {
		mockGetQuery.mockReturnValue( {
			payment_method_type: 'card',
		} );
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: false,
		} );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 0, sources: [], types: [] },
			isLoading: false,
		} );
		render( <FeesReport /> );
		expect( screen.queryByText( 'No fees yet' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'No fees to display' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Fees will appear here.' )
		).toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: 'Reset' } ) ).toBeEnabled();
	} );

	it( 'does not render the export action in the Fees DataViews controls', () => {
		render( <FeesReport /> );
		expect(
			screen.queryByRole( 'button', { name: /export/i } )
		).not.toBeInTheDocument();
	} );

	it( 'announces the report-loaded status when the loading flag flips false', () => {
		// Start in loading state.
		mockUseReportsFees.mockReturnValue( {
			feesRows: [],
			feesError: {},
			isLoading: true,
		} );
		const { rerender } = render( <FeesReport /> );

		// Transition to ready with data.
		mockUseReportsFees.mockReturnValue( {
			feesRows: [ baseRow ],
			feesError: {},
			isLoading: false,
		} );
		rerender( <FeesReport /> );

		expect( mockSpeak ).toHaveBeenCalledWith(
			expect.stringMatching( /loaded/i )
		);
	} );
} );
