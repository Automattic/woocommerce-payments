/** @format */
/* eslint-disable camelcase */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from '../../util';

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
			byId: {
				po_mock1: mockDeposits[ 0 ],
			},
			queries: {
				earlierQuery: {
					data: [ 'po_mock1' ],
				},
			},
		};

		const reduced = reducer(
			before,
			{
				type: types.SET_DEPOSITS,
				data: mockDeposits.slice( 1 ),
				query: mockQuery,
			}
		);

		const after = {
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
} );
