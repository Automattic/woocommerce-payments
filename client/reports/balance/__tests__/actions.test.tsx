/** @format */

import React from 'react';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { downloadCSVFile } from '@woocommerce/csv-export';
import { recordEvent } from 'tracks';

const mockCreateNotice = jest.fn();
const mockUseReportsBalanceSummary = jest.fn();
const mockUseBalanceDateFilter = jest.fn();
let consoleErrorSpy: jest.SpyInstance | undefined;

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

// Mirror the existing Balance report tests so Balance amount formatting remains
// deterministic when CSV generation touches row labels/values.
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

/**
 * Internal dependencies
 */
import balanceSummaryFixture from 'wcpay/data/reports/fixtures/balance-summary';
import { BalanceActions } from '../actions';
import { getVisibleBalanceRows } from '../rows';
import { getRangeDays } from '../utils';

const mockDownloadCSVFile = downloadCSVFile as jest.MockedFunction<
	typeof downloadCSVFile
>;

const period = {
	start: '2026-04-01T00:00:00.000Z',
	end: '2026-04-30T23:59:59.999Z',
};

const dateFilterValue = {
	operator: 'between',
	value: [ '2026-04-01', '2026-04-30' ],
};

const expectedPayload = {
	currency: balanceSummaryFixture.currency,
	visible_row_count: getVisibleBalanceRows( balanceSummaryFixture ).length,
	range_days: getRangeDays(
		balanceSummaryFixture.period.start,
		balanceSummaryFixture.period.end
	),
};

const renderBalanceActions = () => render( <BalanceActions /> );

beforeEach( () => {
	mockCreateNotice.mockReset();
	mockDownloadCSVFile.mockReset();
	mockUseReportsBalanceSummary.mockReset();
	mockUseBalanceDateFilter.mockReset();
	mockRecordEvent.mockReset();
	consoleErrorSpy = undefined;

	mockUseBalanceDateFilter.mockReturnValue( {
		value: dateFilterValue,
		period,
		hasDateFilterValue: true,
		setValue: jest.fn(),
	} );
	mockUseReportsBalanceSummary.mockReturnValue( {
		summary: balanceSummaryFixture,
		error: {},
		isLoading: false,
	} );
} );

afterEach( () => {
	jest.restoreAllMocks();
} );

describe( 'BalanceActions Tracks', () => {
	it( 'records export click and success events with the visible Balance context', async () => {
		renderBalanceActions();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		await waitFor( () =>
			expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 )
		);
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			1,
			'wcpay_reports_balance_export_click',
			expectedPayload
		);
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			2,
			'wcpay_reports_balance_export_success',
			expectedPayload
		);
		expect( mockDownloadCSVFile ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'records export errors with the failure message and preserves notice behavior', async () => {
		consoleErrorSpy = jest
			.spyOn( console, 'error' )
			.mockImplementation( () => undefined );
		const error = new Error( 'download failed' );
		mockDownloadCSVFile.mockImplementationOnce( () => {
			throw error;
		} );

		renderBalanceActions();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);

		await waitFor( () =>
			expect( mockRecordEvent ).toHaveBeenCalledTimes( 2 )
		);
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			1,
			'wcpay_reports_balance_export_click',
			expectedPayload
		);
		expect( mockRecordEvent ).toHaveBeenNthCalledWith(
			2,
			'wcpay_reports_balance_export_error',
			{
				...expectedPayload,
				error_message: 'download failed',
			}
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

	it( 'records print clicks before invoking the browser print preview', async () => {
		const print = jest.fn();
		Object.defineProperty( window, 'print', {
			configurable: true,
			value: print,
		} );

		renderBalanceActions();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Print' } )
		);

		expect( mockRecordEvent ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent ).toHaveBeenCalledWith(
			'wcpay_reports_balance_print_click',
			expectedPayload
		);
		expect( print ).toHaveBeenCalledTimes( 1 );
		expect( mockRecordEvent.mock.invocationCallOrder[ 0 ] ).toBeLessThan(
			print.mock.invocationCallOrder[ 0 ]
		);
	} );

	it( 'does not record action events when Balance actions are disabled', async () => {
		mockUseBalanceDateFilter.mockReturnValue( {
			value: undefined,
			period,
			hasDateFilterValue: false,
			setValue: jest.fn(),
		} );
		const print = jest.fn();
		Object.defineProperty( window, 'print', {
			configurable: true,
			value: print,
		} );

		renderBalanceActions();

		await userEvent.click(
			screen.getByRole( 'button', { name: 'Export' } )
		);
		await userEvent.click(
			screen.getByRole( 'button', { name: 'Print' } )
		);

		expect( mockRecordEvent ).not.toHaveBeenCalled();
		expect( mockDownloadCSVFile ).not.toHaveBeenCalled();
		expect( print ).not.toHaveBeenCalled();
	} );
} );
