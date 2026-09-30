/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Reports reducer tests', () => {
	const mockQuery = { paged: '2', perPage: '50' };
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
	const newRows = [ ...mockRows, ...mockRows ];
	const mockSummary = {
		count: 2,
		total: 3000,
		fees: 150,
	};
	const mockBalanceSummary = {
		currency: 'usd',
		starting_balance: {
			amount: 1000,
		},
		ending_balance: {
			amount: 0,
		},
	};
	const newBalanceSummary = {
		...mockBalanceSummary,
		ending_balance: {
			amount: 500,
		},
	};
	const newSummary = {
		count: 4,
		total: 6000,
		fees: 300,
	};
	const mockError = { code: 'error' };

	const emptyState = {};
	const filledState = {
		[ getResourceId( mockQuery ) ]: {
			data: mockRows,
		},
		summary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockSummary,
			},
		},
		balanceSummary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockBalanceSummary,
			},
		},
	};

	test( 'ignores unrelated actions', () => {
		expect( reducer( emptyState, { type: 'WRONG-TYPE' } ) ).toBe(
			emptyState
		);
		expect( reducer( filledState, { type: 'WRONG-TYPE' } ) ).toBe(
			filledState
		);
	} );

	test( 'stores report fee rows by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_REPORTS_FEES,
			data: mockRows,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			[ getResourceId( mockQuery ) ]: {
				data: mockRows,
				error: undefined,
			},
		} );
	} );

	test( 'updates report fee rows for an existing query resource id', () => {
		const reduced = reducer( filledState, {
			type: types.SET_REPORTS_FEES,
			data: newRows,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			...filledState,
			[ getResourceId( mockQuery ) ]: {
				data: newRows,
				error: undefined,
			},
		} );
	} );

	test( 'stores report fee row errors by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_ERROR_FOR_REPORTS_FEES,
			error: mockError,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			[ getResourceId( mockQuery ) ]: {
				error: mockError,
			},
		} );
	} );

	test( 'stores report fees summaries by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_REPORTS_FEES_SUMMARY,
			data: mockSummary,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockSummary,
					error: undefined,
				},
			},
		} );
	} );

	test( 'updates report fees summaries for an existing query resource id', () => {
		const reduced = reducer( filledState, {
			type: types.SET_REPORTS_FEES_SUMMARY,
			data: newSummary,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			...filledState,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: newSummary,
					error: undefined,
				},
			},
		} );
	} );

	test( 'stores report fees summary errors by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_ERROR_FOR_REPORTS_FEES_SUMMARY,
			error: mockError,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					error: mockError,
				},
			},
		} );
	} );

	test( 'stores report balance summaries by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_REPORTS_BALANCE_SUMMARY,
			data: mockBalanceSummary,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			balanceSummary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockBalanceSummary,
					error: undefined,
				},
			},
		} );
	} );

	test( 'updates report balance summaries for an existing query resource id', () => {
		const reduced = reducer( filledState, {
			type: types.SET_REPORTS_BALANCE_SUMMARY,
			data: newBalanceSummary,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			...filledState,
			balanceSummary: {
				[ getResourceId( mockQuery ) ]: {
					data: newBalanceSummary,
					error: undefined,
				},
			},
		} );
	} );

	test( 'stores report balance summary errors by query resource id', () => {
		const reduced = reducer( emptyState, {
			type: types.SET_ERROR_FOR_REPORTS_BALANCE_SUMMARY,
			error: mockError,
			query: mockQuery,
		} );

		expect( reduced ).toStrictEqual( {
			balanceSummary: {
				[ getResourceId( mockQuery ) ]: {
					error: mockError,
				},
			},
		} );
	} );

	describe( 'receiveReports — slice preservation', () => {
		it( 'preserves cached rows when an error arrives after a successful load', () => {
			const query = { paged: 1 };
			const loaded = reducer( undefined, {
				type: types.SET_REPORTS_FEES,
				query,
				data: [ { transaction_id: 'txn_1' } ],
			} );

			const errored = reducer( loaded, {
				type: types.SET_ERROR_FOR_REPORTS_FEES,
				query,
				error: { code: 'rest_failure' },
			} );

			const index = getResourceId( query );
			expect( errored[ index ].data ).toEqual( [
				{ transaction_id: 'txn_1' },
			] );
			expect( errored[ index ].error ).toEqual( {
				code: 'rest_failure',
			} );
		} );

		it( 'clears any prior error when fresh rows arrive', () => {
			const query = { paged: 1 };
			const errored = reducer( undefined, {
				type: types.SET_ERROR_FOR_REPORTS_FEES,
				query,
				error: { code: 'rest_failure' },
			} );

			const reloaded = reducer( errored, {
				type: types.SET_REPORTS_FEES,
				query,
				data: [ { transaction_id: 'txn_2' } ],
			} );

			const index = getResourceId( query );
			expect( reloaded[ index ].data ).toEqual( [
				{ transaction_id: 'txn_2' },
			] );
			expect( reloaded[ index ].error ).toBeUndefined();
		} );

		it( 'preserves summary data when summary error arrives after success', () => {
			const query = { paged: 1 };
			const loaded = reducer( undefined, {
				type: types.SET_REPORTS_FEES_SUMMARY,
				query,
				data: { count: 42 },
			} );

			const errored = reducer( loaded, {
				type: types.SET_ERROR_FOR_REPORTS_FEES_SUMMARY,
				query,
				error: { code: 'rest_failure' },
			} );

			const index = getResourceId( query );
			expect( errored.summary[ index ].data ).toEqual( { count: 42 } );
			expect( errored.summary[ index ].error ).toEqual( {
				code: 'rest_failure',
			} );
		} );

		it( 'preserves Balance summary data when summary error arrives after success', () => {
			const query = {
				dateStart: '2024-03-01T00:00:00',
				dateEnd: '2024-03-31T23:59:59',
				currency: 'usd',
			};
			const loaded = reducer( undefined, {
				type: types.SET_REPORTS_BALANCE_SUMMARY,
				query,
				data: mockBalanceSummary,
			} );

			const errored = reducer( loaded, {
				type: types.SET_ERROR_FOR_REPORTS_BALANCE_SUMMARY,
				query,
				error: { code: 'rest_failure' },
			} );

			const index = getResourceId( query );
			expect( errored.balanceSummary[ index ].data ).toEqual(
				mockBalanceSummary
			);
			expect( errored.balanceSummary[ index ].error ).toEqual( {
				code: 'rest_failure',
			} );
		} );

		it( 'isolates cache slices across distinct queries', () => {
			const query1 = { paged: 1 };
			const query2 = { paged: 2 };

			let state = reducer( undefined, {
				type: types.SET_REPORTS_FEES,
				query: query1,
				data: [ { transaction_id: 'txn_1' } ],
			} );
			state = reducer( state, {
				type: types.SET_REPORTS_FEES,
				query: query2,
				data: [ { transaction_id: 'txn_2' } ],
			} );
			state = reducer( state, {
				type: types.SET_ERROR_FOR_REPORTS_FEES,
				query: query1,
				error: { code: 'rest_failure' },
			} );

			const index1 = getResourceId( query1 );
			const index2 = getResourceId( query2 );

			expect( state[ index1 ].data ).toEqual( [
				{ transaction_id: 'txn_1' },
			] );
			expect( state[ index1 ].error ).toEqual( {
				code: 'rest_failure',
			} );
			expect( state[ index2 ].data ).toEqual( [
				{ transaction_id: 'txn_2' },
			] );
			expect( state[ index2 ].error ).toBeUndefined();
		} );
	} );
} );
