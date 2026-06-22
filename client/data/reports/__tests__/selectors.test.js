/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import {
	getReportsFees,
	getReportsFeesError,
	getReportsFeesSummary,
	getReportsFeesSummaryError,
} from '../selectors';

describe( 'Reports selectors', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockSummaryQuery = { dateBetween: [ '2026-04-01', '2026-04-30' ] };
	const mockRows = [
		{
			transaction_id: 'txn_123',
			amount: 1000,
			fees: 50,
		},
		{
			transaction_id: 'txn_456',
			amount: 2000,
			fees: 100,
		},
	];
	const mockSummary = {
		count: 2,
		total: 3000,
		fees: 150,
	};
	const mockError = {
		code: 'error',
		message: 'Something went wrong.',
	};

	const emptyState = {
		reports: {
			summary: {},
		},
	};

	const filledSuccessState = {
		reports: {
			[ getResourceId( mockQuery ) ]: {
				data: mockRows,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					data: mockSummary,
				},
			},
		},
	};

	const filledErrorState = {
		reports: {
			[ getResourceId( mockQuery ) ]: {
				error: mockError,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					error: mockError,
				},
			},
		},
	};

	test( 'returns an empty fees list when the report rows are empty', () => {
		expect( getReportsFees( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'returns stable empty values for unresolved report data', () => {
		expect( getReportsFees( emptyState, mockQuery ) ).toBe(
			getReportsFees( emptyState, mockQuery )
		);
		expect( getReportsFeesError( emptyState, mockQuery ) ).toBe(
			getReportsFeesError( emptyState, mockQuery )
		);
		expect( getReportsFeesSummary( emptyState, mockSummaryQuery ) ).toBe(
			getReportsFeesSummary( emptyState, mockSummaryQuery )
		);
		expect(
			getReportsFeesSummaryError( emptyState, mockSummaryQuery )
		).toBe( getReportsFeesSummaryError( emptyState, mockSummaryQuery ) );
	} );

	test( 'returns the fees rows from state', () => {
		expect( getReportsFees( filledSuccessState, mockQuery ) ).toBe(
			mockRows
		);
	} );

	test( 'returns an empty fees error when no error exists', () => {
		expect( getReportsFeesError( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'returns the fees error from state', () => {
		expect( getReportsFeesError( filledErrorState, mockQuery ) ).toBe(
			mockError
		);
	} );

	test( 'returns an empty fees summary when the summary is empty', () => {
		expect(
			getReportsFeesSummary( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'returns the fees summary from state', () => {
		expect(
			getReportsFeesSummary( filledSuccessState, mockSummaryQuery )
		).toBe( mockSummary );
	} );

	test( 'returns an empty fees summary error when no error exists', () => {
		expect(
			getReportsFeesSummaryError( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'returns the fees summary error from state', () => {
		expect(
			getReportsFeesSummaryError( filledErrorState, mockSummaryQuery )
		).toBe( mockError );
	} );
} );
