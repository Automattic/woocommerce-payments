
/**
 * Internal dependencies
 */
import { getResourceId } from '../../util';
import { ID_PREFIX } from '../../constants';
import {
	getTransactions,
	getTransactionsError,
	getTransactionsSummary,
	getTransactionsSummaryError,
} from '../selectors';

describe( 'Transactions selectors', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: 50 };
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

	// Initial empty state.
	const emptyState = {
		transactions: {},
	};

	// Sections in state are empty.
	const emptySummaryState = {
		transactions: {
			summary: {},
		},
	};
	const emptySummaryErrorState = {
		transactions: {
			summary: {
				error: {},
			},
		},
	};

	// State is populated.
	const filledSuccessState = {
		transactions: {
			[ getResourceId( ID_PREFIX.transactions, mockQuery ) ]: {
				data: mockTransactions,
			},
			summary: {
				data: mockSummary,
			},
		},
	};
	const filledErrorState = {
		transactions: {
			[ getResourceId( ID_PREFIX.transactions, mockQuery ) ]: {
				error: mockError,
			},
			summary: {
				error: mockError,
			},
		},
	};

	test( 'Returns empty transactions list when state is uninitialized', () => {
		expect( getTransactions( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns empty transactions list when transactions list is empty', () => {
		expect( getTransactions( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns transactions list from state', () => {
		const expected = mockTransactions;
		expect( getTransactions( filledSuccessState, mockQuery ) ).toBe( expected );
	} );

	test( 'Returns empty transactions list error when state is uninitialized', () => {
		expect( getTransactionsError( emptyState, mockQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns empty transactions list error when error is empty', () => {
		expect( getTransactionsError( emptyState, mockQuery ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions list error from state', () => {
		const expected = mockError;
		expect( getTransactionsError( filledErrorState, mockQuery ) ).toBe( expected );
	} );

	test( 'Returns empty transactions summary when state is uninitialized', () => {
		expect( getTransactionsSummary( emptyState ) ).toStrictEqual( {} );
	} );

	test( 'Returns empty transactions summary when transactions summary is empty', () => {
		expect( getTransactionsSummary( emptySummaryState ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary from state', () => {
		const expected = mockSummary;
		expect( getTransactionsSummary( filledSuccessState ) ).toBe( expected );
	} );

	test( 'Returns empty transactions summary error when state is uninitialized', () => {
		expect( getTransactionsSummaryError( emptyState ) ).toStrictEqual( {} );
	} );

	test( 'Returns empty transactions summary error when error is empty', () => {
		expect( getTransactionsSummaryError( emptySummaryErrorState ) ).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary error from state', () => {
		const expected = mockError;
		expect( getTransactionsSummaryError( filledErrorState ) ).toBe( expected );
	} );
} );
