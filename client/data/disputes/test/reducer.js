/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';

describe( 'Disputes reducer tests', () => {
	const mockQuery = { paged: '2', perPage: '50' };
	const mockDisputes = [
		{
			id: 'dp_mock1',
			reason: 'product_unacceptable',
		},
		{
			id: 'dp_mock2',
			reason: 'fraudulent',
		},
	];

	const mockCachedDisputes = [
		{
			dispute_id: 'dp_mock1',
			reason: 'product_unacceptable',
		},
		{
			dispute_id: 'dp_mock2',
			reason: 'fraudulent',
		},
	];

	test( 'New individual disputes reduced correctly', () => {
		const stateAfterOne = reducer(
			undefined, // Default state.
			{
				type: types.SET_DISPUTE,
				data: mockDisputes[ 0 ],
			}
		);

		expect( stateAfterOne ).toStrictEqual( {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
			},
			cached: {},
			queries: {},
			summary: {},
		} );

		const stateAfterTwo = reducer( stateAfterOne, {
			type: types.SET_DISPUTE,
			data: mockDisputes[ 1 ],
		} );

		expect( stateAfterTwo ).toStrictEqual( {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
				dp_mock2: mockDisputes[ 1 ],
			},
			cached: {},
			queries: {},
			summary: {},
			statusCounts: {},
		} );
	} );

	test( 'New disputes reduced correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_DISPUTES,
				data: mockCachedDisputes,
				query: mockQuery,
			}
		);

		const after = {
			byId: {},
			cached: {
				dp_mock1: mockCachedDisputes[ 0 ],
				dp_mock2: mockCachedDisputes[ 1 ],
			},
			queries: {
				[ getResourceId( mockQuery ) ]: {
					data: [ 'dp_mock1', 'dp_mock2' ],
				},
			},
			summary: {},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'Disputes updated correctly on updated info', () => {
		const before = {
			byId: {},
			queries: {
				earlierQuery: {
					data: [ 'dp_mock1' ],
				},
			},
			cached: {
				dp_mock1: mockCachedDisputes[ 0 ],
			},
			summary: {
				count: 1,
			},
		};

		const reduced = reducer( before, {
			type: types.SET_DISPUTES,
			data: mockCachedDisputes.slice( 1 ),
			query: mockQuery,
		} );

		const after = {
			byId: {},
			cached: {
				dp_mock1: mockCachedDisputes[ 0 ],
				dp_mock2: mockCachedDisputes[ 1 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'dp_mock1' ],
				},
				[ getResourceId( mockQuery ) ]: {
					data: [ 'dp_mock2' ],
				},
			},
			summary: {
				count: 1,
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'New disputes summary reduced correctly', () => {
		const reduced = reducer( undefined, {
			type: types.SET_DISPUTES_SUMMARY,
			query: mockQuery,
			data: {
				count: 42,
			},
		} );

		const after = {
			byId: {},
			queries: {},
			cached: {},
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 42,
					},
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'Disputes summary updated correctly', () => {
		const before = {
			byId: {
				dp_mock1: mockDisputes[ 0 ],
			},
			cached: {
				dp_mock1: mockCachedDisputes[ 0 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'dp_mock1' ],
				},
			},
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 1,
					},
				},
			},
		};

		const reduced = reducer( before, {
			type: types.SET_DISPUTES_SUMMARY,
			query: mockQuery,
			data: {
				count: 42,
			},
		} );

		const after = {
			...before,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 42,
					},
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );
} );
