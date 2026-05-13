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
} );
