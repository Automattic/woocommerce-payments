/** @format */

/**
 * External dependencies
 */
import React from 'react';
import { act, render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import { ReportsPage } from '..';
import { STORE_NAME as WCPAY_STORE_NAME } from 'wcpay/data/constants';
import { getQuery, updateQueryString } from '@woocommerce/navigation';
import { useDispatch } from '@wordpress/data';

jest.mock( '../fees', () => ( {
	FeesReport: ( { onReload }: { onReload?: () => void } ) => (
		<div>
			<div>Fees ledger table</div>
			<button onClick={ onReload }>Reload</button>
		</div>
	),
} ) );

// Stub the Fees + Balance summary hooks so the Export / Print actions in the
// Reports header render without exercising the real @wordpress/data
// selectors (this test only cares about tab navigation behavior).
jest.mock( 'wcpay/data', () => ( {
	useReportsFeesSummary: () => ( {
		feesSummary: { count: 0 },
		isLoading: false,
	} ),
	useReportsBalanceSummary: () => ( {
		summary: {},
		error: {},
		isLoading: false,
	} ),
} ) );

jest.mock( 'wcpay/data/reports/hooks', () => ( {
	useReportsFeesSummary: () => ( {
		feesSummary: { count: 0 },
		isLoading: false,
	} ),
} ) );

// BalanceActions reads the Date filter via its own hook.
jest.mock( '../balance/use-balance-date-filter', () => ( {
	BalanceDateFilterNowContext: jest
		.requireActual( 'react' )
		.createContext( undefined ),
	useBalanceDateFilter: () => ( {
		value: undefined,
		period: { start: '', end: '' },
		hasDateFilterValue: false,
		setValue: jest.fn(),
	} ),
} ) );

const activeBalancePeriod = {
	start: '2026-05-01T00:00:00.000Z',
	end: '2026-05-20T23:59:59.999Z',
};

jest.mock( '../balance', () => ( {
	BalanceReport: ( {
		onReload,
	}: {
		onReload?: ( period?: typeof activeBalancePeriod ) => void;
	} ) => (
		<div>
			<div>Balance summary table</div>
			<button onClick={ () => onReload?.( activeBalancePeriod ) }>
				Reload
			</button>
		</div>
	),
} ) );

jest.mock( '@woocommerce/navigation', () => ( {
	getQuery: jest.fn(),
	updateQueryString: jest.fn(),
} ) );

// Explicit, narrow mock of @wordpress/data. We only rely on `useDispatch` in
// this test, but stub the other commonly-imported helpers so that components
// elsewhere in the render tree fail visibly (rather than silently receiving
// `undefined`) if they grow a new dependency on the data module.
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn( ( mapSelect: ( fn: () => unknown ) => unknown ) =>
		typeof mapSelect === 'function' ? mapSelect( () => ( {} ) ) : undefined
	),
	select: jest.fn( () => ( {} ) ),
	dispatch: jest.fn( () => ( {} ) ),
	withSelect:
		( mapSelect: unknown ) =>
		( Component: React.ComponentType< unknown > ) => {
			void mapSelect;
			return Component;
		},
	withDispatch:
		( mapDispatch: unknown ) =>
		( Component: React.ComponentType< unknown > ) => {
			void mapDispatch;
			return Component;
		},
	register: jest.fn(),
	combineReducers: ( reducers: Record< string, unknown > ) => reducers,
	createReduxStore: jest.fn(),
} ) );

const mockGetQuery = getQuery as jest.Mock;
const mockUpdateQueryString = updateQueryString as jest.Mock;
const mockUseDispatch = useDispatch as jest.Mock;

declare const global: {
	wcpaySettings: {
		accountDefaultCurrency?: string;
		featureFlags: Record< string, boolean >;
		fraudServices: unknown[];
	};
};

describe( 'Reports page tabs', () => {
	const invalidateResolution = jest.fn();
	const invalidateResolutionForStoreSelector = jest.fn();

	const renderReportsPage = async ( props = {} ) => {
		const result = render( <ReportsPage { ...props } /> );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: 'Balance' } )
			).toBeInTheDocument();
		} );

		return result;
	};

	beforeEach( () => {
		global.wcpaySettings = {
			accountDefaultCurrency: 'USD',
			featureFlags: {},
			fraudServices: [],
		};
		mockGetQuery.mockReturnValue( {} );
		mockUpdateQueryString.mockClear();
		invalidateResolution.mockClear();
		invalidateResolutionForStoreSelector.mockClear();
		mockUseDispatch.mockImplementation( ( storeName ) => {
			if ( WCPAY_STORE_NAME === storeName ) {
				return {
					invalidateResolution,
					invalidateResolutionForStoreSelector,
				};
			}
			return {};
		} );
	} );

	it( 'defaults to the Balance tab and renders Balance before Fees', async () => {
		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		const tabs = screen.getAllByRole( 'tab' );

		expect( tabs.map( ( tab ) => tab.textContent ) ).toEqual( [
			'Balance',
			'Fees',
		] );
		expect(
			screen.getByRole( 'tab', { name: 'Balance' } )
		).toHaveAttribute( 'aria-selected', 'true' );
		expect(
			screen.getByText( /reconciliation reports/i )
		).toBeInTheDocument();
		expect(
			screen.getByRole( 'heading', { name: 'Reports', level: 1 } )
		).toBeInTheDocument();
		expect(
			screen.queryByRole( 'navigation', { name: 'Breadcrumb' } )
		).not.toBeInTheDocument();
	} );

	it( 'uses the tab query parameter for direct Fees navigation', async () => {
		mockGetQuery.mockReturnValue( { tab: 'fees' } );

		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		expect( screen.getByRole( 'tab', { name: 'Fees' } ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);
		expect(
			screen.queryByRole( 'navigation', { name: 'Breadcrumb' } )
		).not.toBeInTheDocument();
	} );

	it( 'syncs the active tab from browser history changes', async () => {
		mockGetQuery.mockReturnValue( { tab: 'fees' } );

		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		expect( screen.getByRole( 'tab', { name: 'Fees' } ) ).toHaveAttribute(
			'aria-selected',
			'true'
		);

		mockGetQuery.mockReturnValue( { tab: 'balance' } );
		await act( async () => {
			window.dispatchEvent( new Event( 'popstate' ) );
		} );

		await waitFor( () => {
			expect(
				screen.getByRole( 'tab', { name: 'Balance' } )
			).toHaveAttribute( 'aria-selected', 'true' );
		} );
	} );

	it( 'updates the query string when switching tabs', async () => {
		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		// TabPanel schedules an internal Ariakit tab update outside userEvent's
		// act boundary, so this click needs a narrow wrapper.
		await act( async () => {
			await userEvent.click(
				screen.getByRole( 'tab', { name: 'Fees' } )
			);
		} );

		await waitFor( () => {
			expect( mockUpdateQueryString ).toHaveBeenCalledWith(
				{ tab: 'fees' },
				'/payments/reports'
			);
		} );
		expect( mockUpdateQueryString ).toHaveBeenCalledTimes( 1 );
		expect( screen.getByRole( 'tab', { name: 'Fees' } ) ).toHaveFocus();
	} );

	it( 'reloads the Balance tab in place by invalidating the active Balance period', async () => {
		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		await userEvent.click(
			screen.getByRole( 'button', { name: /Reload/i } )
		);

		expect( invalidateResolution ).toHaveBeenCalledWith(
			'getReportsBalanceSummary',
			[
				{
					dateStart: activeBalancePeriod.start,
					dateEnd: activeBalancePeriod.end,
					currency: 'usd',
				},
			]
		);
	} );

	it( 'does not crash the Reports page when the account default currency is missing', async () => {
		global.wcpaySettings.accountDefaultCurrency = undefined;

		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		await userEvent.click(
			screen.getByRole( 'button', { name: /Reload/i } )
		);

		expect( invalidateResolution ).toHaveBeenCalledWith(
			'getReportsBalanceSummary',
			[
				{
					dateStart: activeBalancePeriod.start,
					dateEnd: activeBalancePeriod.end,
					currency: '',
				},
			]
		);
	} );

	it( 'reloads the Fees tab by invalidating all Fees selector resolutions', async () => {
		mockGetQuery.mockReturnValue( { tab: 'fees' } );

		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		await userEvent.click(
			screen.getByRole( 'button', { name: /Reload/i } )
		);

		expect( invalidateResolutionForStoreSelector ).toHaveBeenCalledWith(
			'getReportsFees'
		);
		expect( invalidateResolutionForStoreSelector ).toHaveBeenCalledWith(
			'getReportsFeesSummary'
		);
	} );

	it( 'renders the Fees report when Fees tab is active', async () => {
		mockGetQuery.mockReturnValue( { tab: 'fees' } );

		await renderReportsPage( {
			now: new Date( '2026-05-06T12:00:00Z' ),
		} );

		expect( screen.getByText( 'Fees ledger table' ) ).toBeInTheDocument();
	} );
} );
