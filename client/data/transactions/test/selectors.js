
/**
 * Internal dependencies
 */
import { getResourceId } from '../../util';
import {
	getTransactions,
	getTransactionsError,
	getTransactionsSummary,
	getTransactionsSummaryError,
} from '../selectors';

describe( 'Transactions selectors', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: 50, depositId: null };
	const mockSummaryQuery = { depositId: null };
	const mockTransactions = [
		{
			id: 1234,
			amount: 1000,
			fees: 50,
			net: 950,
		},
		{
			id: 1235,
			amount: 2000,
			fees: 100,
			net: 1900,
		},
	];
	const mockSummary = {
		total: 1000,
		fees: 50,
		net: 950,
	};
	const mockError = {
		error: 'Something went wrong!',
		code: 400,
	};

	// Sections in initial state are empty.
	const emptyState = {
		transactions: {
			summary: {},
		},
	};
	const emptySummaryErrorState = {
		transactions: {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					error: {},
				},
			},
		},
	};

	// State is populated.
	const filledSuccessState = {
		transactions: {
			[ getResourceId( mockQuery ) ]: {
				data: mockTransactions,
			},
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					data: mockSummary,
				},
			},
		},
	};
	const filledErrorState = {
		transactions: {
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

	test( 'Returns empty transactions list when transactions list is empty', () => {
		expect( getTransactions( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns transactions list from state', () => {
		const expected = mockTransactions;
		expect( getTransactions( filledSuccessState, mockQuery ) ).toBe( expected );
	} );

	test( 'Returns empty transactions list error when error is empty', () => {
		expect( getTransactionsError( emptyState, mockQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions list error from state', () => {
		const expected = mockError;
		expect( getTransactionsError( filledErrorState, mockQuery ) ).toBe( expected );
	} );

	test( 'Returns empty transactions summary when transactions summary is empty', () => {
		expect( getTransactionsSummary( emptyState, mockSummaryQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary from state', () => {
		const expected = mockSummary;
		expect( getTransactionsSummary( filledSuccessState, mockSummaryQuery ) ).toBe( expected );
	} );

	test( 'Returns empty transactions summary error when state is uninitialized', () => {
		expect( getTransactionsSummaryError( emptyState, mockSummaryQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns empty transactions summary error when error is empty', () => {
		expect( getTransactionsSummaryError( emptySummaryErrorState, mockSummaryQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary error from state', () => {
		const expected = mockError;
		expect( getTransactionsSummaryError( filledErrorState, mockSummaryQuery ) ).toBe( expected );
	} );
} );
