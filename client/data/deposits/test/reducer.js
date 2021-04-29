/** @format */
/* eslint-disable camelcase */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Deposits reducer tests', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDeposits = [
		{
			id: 'po_mock1',
			amount: 2000,
		},
		{
			id: 'po_mock2',
			amount: 3000,
		},
	];
	const mockInstantDeposit = {
		id: 'po_mock1',
		object: 'payout',
		amount: 2000,
	};
	const mockOverview = {
		last_deposit: mockDeposits[ 0 ],
		next_deposit: mockDeposits[ 1 ],
		balance: { object: 'balance' },
		deposits_schedule: { interval: 'daily' },
	};
	const mockEmptyState = {
		byId: {},
		queries: {},
		count: 0,
		instant: {},
	};

	test( 'New individual deposits reduced correctly', () => {
		const stateAfterOne = reducer(
			undefined, // Default state.
			{
				type: types.SET_DEPOSIT,
				data: mockDeposits[ 0 ],
			}
		);

		expect( stateAfterOne ).toStrictEqual( {
			...mockEmptyState,
			byId: {
				po_mock1: mockDeposits[ 0 ],
			},
		} );

		const stateAfterTwo = reducer( stateAfterOne, {
			type: types.SET_DEPOSIT,
			data: mockDeposits[ 1 ],
		} );

		expect( stateAfterTwo ).toStrictEqual( {
			...mockEmptyState,
			byId: {
				po_mock1: mockDeposits[ 0 ],
				po_mock2: mockDeposits[ 1 ],
			},
		} );
	} );

	test( 'New deposits reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_DEPOSITS,
				data: mockDeposits,
				query: mockQuery,
			}
		);

		const after = {
			...mockEmptyState,
			byId: {
				po_mock1: mockDeposits[ 0 ],
				po_mock2: mockDeposits[ 1 ],
			},
			queries: {
				[ getResourceId( mockQuery ) ]: {
					data: [ 'po_mock1', 'po_mock2' ],
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'Deposits updated correctly on updated info', () => {
		const before = {
			...mockEmptyState,
			byId: {
				po_mock1: mockDeposits[ 0 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'po_mock1' ],
				},
			},
		};

		const reduced = reducer( before, {
			type: types.SET_DEPOSITS,
			data: mockDeposits.slice( 1 ),
			query: mockQuery,
		} );

		const after = {
			...mockEmptyState,
			byId: {
				po_mock1: mockDeposits[ 0 ],
				po_mock2: mockDeposits[ 1 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'po_mock1' ],
				},
				[ getResourceId( mockQuery ) ]: {
					data: [ 'po_mock2' ],
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'Deposits overview is reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_DEPOSITS_OVERVIEW,
				data: mockOverview,
			}
		);

		expect( reduced ).toStrictEqual( {
			...mockEmptyState,
			overview: {
				data: mockOverview,
			},
		} );
	} );

	test( 'Deposits overview error is reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_ERROR_FOR_DEPOSITS_OVERVIEW,
				error: { code: 'error' },
			}
		);

		expect( reduced ).toStrictEqual( {
			...mockEmptyState,
			overview: {
				error: { code: 'error' },
			},
		} );
	} );

	test( 'Deposits count is reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_DEPOSITS_COUNT,
				data: 10,
			}
		);

		expect( reduced ).toStrictEqual( {
			...mockEmptyState,
			count: 10,
		} );
	} );

	test( 'Deposits query error is reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_ERROR_FOR_DEPOSIT_QUERY,
				error: { code: 'error' },
			}
		);

		expect( reduced ).toStrictEqual( {
			...mockEmptyState,
			queries: {
				'{}': {
					error: { code: 'error' },
				},
			},
		} );
	} );

	test( 'New instant deposit reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_INSTANT_DEPOSIT,
				data: mockInstantDeposit,
			}
		);

		const after = {
			...mockEmptyState,
			instant: {
				data: mockInstantDeposit,
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );
} );
