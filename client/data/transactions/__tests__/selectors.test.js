/** @format */

/**
 * Internal dependencies
 */
import { getResourceId } from 'utils/data';
import {
	getFraudOutcomeTransactions,
	getFraudOutcomeTransactionsError,
	getFraudOutcomeTransactionsSummary,
	getFraudOutcomeTransactionsSummaryError,
	getTransactions,
	getTransactionsError,
	getTransactionsSummary,
	getTransactionsSummaryError,
} from '../selectors';

describe( 'Transactions selectors', () => {
	// Mock objects.
	const mockQuery = { paged: '2', perPage: '50', depositId: null };
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
	const mockBlockedTransactions = [
		{
			id: 1236,
			amount: 3000,
			fees: 150,
			net: 2850,
		},
	];
	const mockBlockedSummary = {
		total: 3000,
		fees: 150,
		net: 2850,
	};
	const mockBlockedError = {
		error: 'Blocked transactions failed to load',
		code: 500,
	};
	const fraudOutcomeMocks = {
		review: {
			transactions: mockTransactions,
			summary: mockSummary,
			error: mockError,
		},
		block: {
			transactions: mockBlockedTransactions,
			summary: mockBlockedSummary,
			error: mockBlockedError,
		},
	};

	// Sections in initial state are empty.
	const emptyState = {
		transactions: {
			summary: {},
			fraudProtection: {
				review: {
					summary: {},
				},
				block: {
					summary: {},
				},
			},
		},
	};
	const emptySummaryErrorState = {
		transactions: {
			summary: {
				[ getResourceId( mockSummaryQuery ) ]: {
					error: {},
				},
			},
			fraudProtection: {
				review: {
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							error: {},
						},
					},
				},
				block: {
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							error: {},
						},
					},
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
			fraudProtection: {
				review: {
					[ getResourceId( mockQuery ) ]: {
						data: mockTransactions,
					},
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							data: mockSummary,
						},
					},
				},
				block: {
					[ getResourceId( mockQuery ) ]: {
						data: mockBlockedTransactions,
					},
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							data: mockBlockedSummary,
						},
					},
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
			fraudProtection: {
				review: {
					[ getResourceId( mockQuery ) ]: {
						error: mockError,
					},
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							error: mockError,
						},
					},
				},
				block: {
					[ getResourceId( mockQuery ) ]: {
						error: mockBlockedError,
					},
					summary: {
						[ getResourceId( mockSummaryQuery ) ]: {
							error: mockBlockedError,
						},
					},
				},
			},
		},
	};

	test( 'Returns empty transactions list when transactions list is empty', () => {
		expect( getTransactions( emptyState, mockQuery ) ).toStrictEqual( [] );
	} );

	test( 'Returns transactions list from state', () => {
		const expected = mockTransactions;
		expect( getTransactions( filledSuccessState, mockQuery ) ).toBe(
			expected
		);
	} );

	test( 'Returns empty transactions list error when error is empty', () => {
		expect( getTransactionsError( emptyState, mockQuery ) ).toStrictEqual(
			{}
		);
	} );

	test( 'Returns transactions list error from state', () => {
		const expected = mockError;
		expect( getTransactionsError( filledErrorState, mockQuery ) ).toBe(
			expected
		);
	} );

	test( 'Returns empty transactions summary when transactions summary is empty', () => {
		expect(
			getTransactionsSummary( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary from state', () => {
		const expected = mockSummary;
		expect(
			getTransactionsSummary( filledSuccessState, mockSummaryQuery )
		).toBe( expected );
	} );

	test( 'Returns empty transactions summary error when state is uninitialized', () => {
		expect(
			getTransactionsSummaryError( emptyState, mockSummaryQuery )
		).toStrictEqual( {} );
	} );

	test( 'Returns empty transactions summary error when error is empty', () => {
		expect(
			getTransactionsSummaryError(
				emptySummaryErrorState,
				mockSummaryQuery
			)
		).toStrictEqual( {} );
	} );

	test( 'Returns transactions summary error from state', () => {
		const expected = mockError;
		expect(
			getTransactionsSummaryError( filledErrorState, mockSummaryQuery )
		).toBe( expected );
	} );

	describe( 'Fraud outcome transactions', () => {
		[ 'review', 'block' ].forEach( ( status ) => {
			test( `Returns empty transactions list when transactions list is empty - ${ status }`, () => {
				expect(
					getFraudOutcomeTransactions( emptyState, status, mockQuery )
				).toStrictEqual( [] );
			} );

			test( `Returns transactions list from state - ${ status }`, () => {
				const expected = fraudOutcomeMocks[ status ].transactions;
				expect(
					getFraudOutcomeTransactions(
						filledSuccessState,
						status,
						mockQuery
					)
				).toBe( expected );
			} );

			test( `Returns empty transactions list error when error is empty - ${ status }`, () => {
				expect(
					getFraudOutcomeTransactionsError(
						emptyState,
						status,
						mockQuery
					)
				).toStrictEqual( {} );
			} );

			test( `Returns transactions list error from state - ${ status }`, () => {
				const expected = fraudOutcomeMocks[ status ].error;
				expect(
					getFraudOutcomeTransactionsError(
						filledErrorState,
						status,
						mockQuery
					)
				).toBe( expected );
			} );

			test( `Returns empty transactions summary when transactions summary is empty - ${ status }`, () => {
				expect(
					getFraudOutcomeTransactionsSummary(
						emptyState,
						status,
						mockSummaryQuery
					)
				).toStrictEqual( {} );
			} );

			test( `Returns transactions summary from state - ${ status }`, () => {
				const expected = fraudOutcomeMocks[ status ].summary;
				expect(
					getFraudOutcomeTransactionsSummary(
						filledSuccessState,
						status,
						mockSummaryQuery
					)
				).toBe( expected );
			} );

			test( `Returns empty transactions summary error when state is uninitialized - ${ status }`, () => {
				expect(
					getFraudOutcomeTransactionsSummaryError(
						emptyState,
						status,
						mockSummaryQuery
					)
				).toStrictEqual( {} );
			} );

			test( `Returns empty transactions summary error when error is empty - ${ status }`, () => {
				expect(
					getFraudOutcomeTransactionsSummaryError(
						emptySummaryErrorState,
						status,
						mockSummaryQuery
					)
				).toStrictEqual( {} );
			} );

			test( `Returns transactions summary error from state - ${ status }`, () => {
				const expected = fraudOutcomeMocks[ status ].error;
				expect(
					getFraudOutcomeTransactionsSummaryError(
						filledErrorState,
						status,
						mockSummaryQuery
					)
				).toBe( expected );
			} );
		} );
	} );
} );
