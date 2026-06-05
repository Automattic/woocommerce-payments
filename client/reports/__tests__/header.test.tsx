/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { ReportsHeader } from '../header';

const requestReportExport = jest.fn();
const createNotice = jest.fn();
const mockGetQuery = jest.fn();
const mockUseReportsFeesSummary = jest.fn();
const mockGetReportsFeesSummary = jest.fn();

jest.mock( 'wcpay/hooks/use-report-export', () => ( {
	useReportExport: () => ( {
		requestReportExport,
		isExportInProgress: false,
	} ),
} ) );

jest.mock( 'wcpay/data', () => ( {
	useReportsFeesSummary: ( q: unknown ) => mockUseReportsFeesSummary( q ),
	// BalanceActions renders inside the header on the Balance tab and
	// subscribes to the Balance summary; the header test only exercises
	// existence/absence, so this stub is enough.
	useReportsBalanceSummary: () => ( {
		summary: {},
		error: {},
		isLoading: false,
	} ),
} ) );

jest.mock( 'wcpay/data/reports/hooks', () => ( {
	useReportsFeesSummary: ( q: unknown ) => mockUseReportsFeesSummary( q ),
} ) );

// BalanceActions also reads the Date filter via this hook.
jest.mock( '../balance/use-balance-date-filter', () => ( {
	useBalanceDateFilter: () => ( {
		value: undefined,
		period: { start: '', end: '' },
		hasDateFilterValue: false,
		setValue: jest.fn(),
	} ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( { createNotice } ),
	select: () => ( {
		getReportsFeesSummary: ( query: unknown ) =>
			mockGetReportsFeesSummary( query ),
	} ),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: () => mockGetQuery(),
} ) );

declare const global: {
	wcpaySettings?: { currentUserEmail: string };
};

describe( 'ReportsHeader', () => {
	const filteredQuery = {
		tab: 'fees',
		payment_method_type: 'card',
		// Plain local-time strings match how date filters land in `getQuery()`
		// from the date-range picker. The forced America/New_York TZ in
		// jest-global-setup makes the start/end-of-day → UTC math deterministic.
		date_after: '2026-01-15 12:00:00',
		date_before: '2026-01-15 12:00:00',
	};

	beforeEach( () => {
		requestReportExport.mockClear();
		createNotice.mockClear();
		mockGetQuery.mockReturnValue( filteredQuery );
		mockGetReportsFeesSummary.mockReset().mockReturnValue( undefined );
		mockUseReportsFeesSummary.mockReturnValue( {
			feesSummary: { count: 42 },
			isLoading: false,
		} );
		global.wcpaySettings = {
			...( global.wcpaySettings ?? {} ),
			currentUserEmail: 'merchant@example.com',
		};
	} );

	it( 'renders the Export and Print actions when the Balance tab is active', async () => {
		render( <ReportsHeader activeTab="balance" /> );

		// Balance tab gets its own pair of header actions (Print + Export)
		// driven by the Balance summary state, mirroring the Fees layout.
		expect(
			await screen.findByRole( 'button', { name: /export/i } )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: /print/i } )
		).toBeInTheDocument();
	} );

	it( 'renders the Export button when the Fees tab is active', () => {
		render( <ReportsHeader activeTab="fees" /> );

		expect(
			screen.getByRole( 'button', { name: /export/i } )
		).toBeInTheDocument();
	} );

	it( 'requests an export with Fees download URLs on click', async () => {
		render( <ReportsHeader activeTab="fees" /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /export/i } )
		);

		expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		const args = requestReportExport.mock.calls[ 0 ][ 0 ];
		expect( args.exportRequestURL ).toContain(
			'/wc/v3/payments/reports/fees/download'
		);
		expect( args.exportRequestURL ).toContain(
			'user_email=merchant%40example.com'
		);
		expect( args.exportRequestURL ).toContain( 'payment_method_type=card' );
		expect( args.exportRequestURL ).toContain( 'locale=en_US' );
		// Date filters from the URL query must reach the export URL —
		// otherwise the file would not match what the merchant sees on screen.
		// EST → UTC: start-of-day 00:00 EST = 05:00 UTC; end-of-day 23:59:59
		// EST = 04:59:59 UTC the next day.
		expect( args.exportRequestURL ).toContain(
			'date_after=2026-01-15%2005%3A00%3A00'
		);
		expect( args.exportRequestURL ).toContain(
			'date_before=2026-01-16%2004%3A59%3A59'
		);
		expect( args.exportFileAvailabilityEndpoint ).toBe(
			'/wc/v3/payments/reports/fees/download'
		);
		expect( args.userEmail ).toBe( 'merchant@example.com' );
	} );

	it( 'shows a success notice on click', async () => {
		render( <ReportsHeader activeTab="fees" /> );

		await userEvent.click(
			screen.getByRole( 'button', { name: /export/i } )
		);

		expect( createNotice ).toHaveBeenCalledWith(
			'success',
			expect.stringContaining( 'merchant@example.com' )
		);
	} );

	describe( 'large-export confirmation guard', () => {
		// Match the threshold in `FeesExportButton`. Aligns with Transactions
		// (which Fees data is 1:1 with) — see confirmThreshold in
		// client/reports/fees-export-button.tsx.
		const confirmThreshold = 10000;
		const originalConfirm = window.confirm;
		let confirmMock: jest.Mock;

		beforeEach( () => {
			confirmMock = jest.fn();
			window.confirm = confirmMock;
		} );

		afterEach( () => {
			window.confirm = originalConfirm;
		} );

		it( 'does not prompt for confirmation when total rows are under the threshold', async () => {
			mockGetQuery.mockReturnValue( { tab: 'fees' } );
			mockUseReportsFeesSummary.mockReturnValue( {
				feesSummary: { count: confirmThreshold - 1 },
				isLoading: false,
			} );

			render( <ReportsHeader activeTab="fees" /> );
			await userEvent.click(
				screen.getByRole( 'button', { name: /export/i } )
			);

			expect( confirmMock ).not.toHaveBeenCalled();
			expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'prompts for confirmation at the threshold boundary', async () => {
			// Pin the `>=` semantics of the guard: an off-by-one regression
			// (`>=` → `>`) would let exactly-threshold exports through silently.
			mockGetQuery.mockReturnValue( { tab: 'fees' } );
			mockUseReportsFeesSummary.mockReturnValue( {
				feesSummary: { count: confirmThreshold },
				isLoading: false,
			} );
			confirmMock.mockReturnValue( true );

			render( <ReportsHeader activeTab="fees" /> );
			await userEvent.click(
				screen.getByRole( 'button', { name: /export/i } )
			);

			expect( confirmMock ).toHaveBeenCalledTimes( 1 );
			expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'does not prompt for confirmation when any filter is active', async () => {
			mockGetQuery.mockReturnValue( {
				tab: 'fees',
				date_after: '2026-01-01 00:00:00',
			} );
			mockUseReportsFeesSummary.mockReturnValue( {
				feesSummary: { count: confirmThreshold + 50000 },
				isLoading: false,
			} );

			render( <ReportsHeader activeTab="fees" /> );
			await userEvent.click(
				screen.getByRole( 'button', { name: /export/i } )
			);

			expect( confirmMock ).not.toHaveBeenCalled();
			expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'prompts and exports when the merchant confirms an unfiltered large export', async () => {
			mockGetQuery.mockReturnValue( { tab: 'fees' } );
			mockUseReportsFeesSummary.mockReturnValue( {
				feesSummary: { count: 25000 },
				isLoading: false,
			} );
			confirmMock.mockReturnValue( true );

			render( <ReportsHeader activeTab="fees" /> );
			await userEvent.click(
				screen.getByRole( 'button', { name: /export/i } )
			);

			expect( confirmMock ).toHaveBeenCalledTimes( 1 );
			expect( confirmMock ).toHaveBeenCalledWith(
				expect.stringContaining( '25000' )
			);
			expect( requestReportExport ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'prompts and aborts when the merchant cancels an unfiltered large export', async () => {
			mockGetQuery.mockReturnValue( { tab: 'fees' } );
			mockUseReportsFeesSummary.mockReturnValue( {
				feesSummary: { count: 25000 },
				isLoading: false,
			} );
			confirmMock.mockReturnValue( false );

			render( <ReportsHeader activeTab="fees" /> );
			await userEvent.click(
				screen.getByRole( 'button', { name: /export/i } )
			);

			expect( confirmMock ).toHaveBeenCalledTimes( 1 );
			expect( requestReportExport ).not.toHaveBeenCalled();
			expect( createNotice ).not.toHaveBeenCalled();
		} );
	} );
} );
