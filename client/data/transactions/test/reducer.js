/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Transactions reducer tests', () => {
	const mockQuery = { paged: '2', perPage: '50' };
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
	const newTransactions = [ ...mockTransactions, ...mockTransactions ];
	const mockSummary = {
		total: 1000,
		fees: 50,
		net: 950,
	};
	const newSummary = {
		total: 5000,
		fees: 100,
		net: 4900,
	};

	const emptyState = {};
	const filledState = {
		[ getResourceId( mockQuery ) ]: {
			data: mockTransactions,
		},
		summary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockSummary,
			},
		},
		fraudProtection: {
			block: {
				[ getResourceId( mockQuery ) ]: {
					data: mockTransactions,
				},
				summary: {
					[ getResourceId( mockQuery ) ]: {
						data: mockSummary,
					},
				},
			},
			review: {
				[ getResourceId( mockQuery ) ]: {
					data: mockTransactions,
				},
				summary: {
					[ getResourceId( mockQuery ) ]: {
						data: mockSummary,
					},
				},
			},
		},
	};

	test( 'Unrelated action is ignored', () => {
		expect( reducer( emptyState, { type: 'WRONG-TYPE' } ) ).toBe(
			emptyState
		);
		expect( reducer( filledState, { type: 'WRONG-TYPE' } ) ).toBe(
			filledState
		);
	} );

	test( 'New transactions reduced correctly', () => {
		// Set up mock data
		const expected = {
			[ getResourceId( mockQuery ) ]: {
				data: mockTransactions,
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_TRANSACTIONS,
			data: mockTransactions,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Transactions updated correctly on updated info', () => {
		const expected = {
			...filledState,
			[ getResourceId( mockQuery ) ]: {
				data: newTransactions,
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_TRANSACTIONS,
			data: newTransactions,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'New transactions summary reduced correctly', () => {
		const expected = {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockSummary,
				},
			},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_TRANSACTIONS_SUMMARY,
			data: mockSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Transactions summary updated correctly on updated info', () => {
		const expected = {
			...filledState,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: newSummary,
				},
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_TRANSACTIONS_SUMMARY,
			data: newSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	describe( 'Fraud outcome transactions', () => {
		[ 'review', 'block' ].forEach( ( status ) => {
			test( `New fraud outcome transactions reduced correctly - ${ status }`, () => {
				const expected = {
					fraudProtection: {
						[ status ]: {
							[ getResourceId( mockQuery ) ]: {
								data: mockTransactions,
							},
						},
					},
				};

				const reduced = reducer( emptyState, {
					type: types.SET_FRAUD_OUTCOME_TRANSACTIONS,
					status,
					data: mockTransactions,
					query: mockQuery,
				} );
				expect( reduced ).toStrictEqual( expected );
			} );

			test( `Fraud outcome transactions for updated correctly on updated info - ${ status }`, () => {
				const expected = {
					...filledState,
					fraudProtection: {
						...filledState.fraudProtection,
						[ status ]: {
							...filledState.fraudProtection[ status ],
							[ getResourceId( mockQuery ) ]: {
								data: newTransactions,
							},
						},
					},
				};

				const reduced = reducer( filledState, {
					type: types.SET_FRAUD_OUTCOME_TRANSACTIONS,
					status,
					data: newTransactions,
					query: mockQuery,
				} );
				expect( reduced ).toStrictEqual( expected );
			} );

			test( `Fraud outcome transactions summary reduced correctly - ${ status }`, () => {
				const expected = {
					fraudProtection: {
						[ status ]: {
							summary: {
								[ getResourceId( mockQuery ) ]: {
									data: mockSummary,
								},
							},
						},
					},
				};

				const reduced = reducer( emptyState, {
					type: types.SET_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY,
					status,
					data: mockSummary,
					query: mockQuery,
				} );
				expect( reduced ).toStrictEqual( expected );
			} );

			test( `Fraud outcome transactions summary for updated correctly on updated info - ${ status }`, () => {
				const expected = {
					...filledState,
					fraudProtection: {
						...filledState.fraudProtection,
						[ status ]: {
							...filledState.fraudProtection[ status ],
							summary: {
								[ getResourceId( mockQuery ) ]: {
									data: newSummary,
								},
							},
						},
					},
				};

				const reduced = reducer( filledState, {
					type: types.SET_FRAUD_OUTCOME_TRANSACTIONS_SUMMARY,
					status,
					data: newSummary,
					query: mockQuery,
				} );
				expect( reduced ).toStrictEqual( expected );
			} );
		} );
	} );
} );
