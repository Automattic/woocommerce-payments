/** @format */

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';
import authorizationsFixture from './authorizations.fixture.json';
import authorizationsSummaryFixture from './authorizations-summary.fixture.json';

describe( 'Authorizations reducer', () => {
	const mockQuery = { paged: '1', perPage: '50' };
	const mockAuthorizations = authorizationsFixture;

	test( 'should reduce new authorizations correctly', () => {
		const reduced = reducer(
			undefined, // Default state.
			{
				type: types.SET_AUTHORIZATIONS,
				data: mockAuthorizations,
				query: mockQuery,
			}
		);

		const after = {
			byId: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			summary: {},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce updated authorizations correctly', () => {
		const before = {
			byId: {},
			earlierQuery: {
				data: mockAuthorizations[ 0 ],
			},
			summary: {},
		};

		const reduced = reducer( before, {
			type: types.SET_AUTHORIZATIONS,
			data: mockAuthorizations.slice( 1 ),
			query: mockQuery,
		} );

		const after = {
			byId: {},
			earlierQuery: {
				data: mockAuthorizations[ 0 ],
			},
			summary: {},
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations.slice( 1 ),
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce authorizations summary correctly', () => {
		const reduced = reducer( undefined, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			query: mockQuery,
			data: authorizationsSummaryFixture,
		} );

		const after = {
			byId: {},
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: authorizationsSummaryFixture,
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce updated authorizations summary correctly', () => {
		const before = {
			byId: {},
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: {
						count: 42,
					},
				},
			},
		};

		const reduced = reducer( before, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			query: mockQuery,
			data: authorizationsSummaryFixture,
		} );

		const after = {
			...before,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: authorizationsSummaryFixture,
				},
			},
		};

		expect( reduced ).toStrictEqual( after );
	} );

	test( 'should reduce new authorization correctly', () => {
		const stateAfterOne = reducer( undefined, {
			type: types.SET_AUTHORIZATION,
			data: mockAuthorizations[ 0 ],
		} );

		expect( stateAfterOne ).toStrictEqual( {
			byId: {
				[ mockAuthorizations[ 0 ].authorization_id ]:
					mockAuthorizations[ 0 ],
			},
			summary: {},
		} );

		const stateAfterTwo = reducer( stateAfterOne, {
			type: types.SET_AUTHORIZATION,
			data: mockAuthorizations[ 1 ],
		} );

		expect( stateAfterTwo ).toStrictEqual( {
			byId: {
				[ mockAuthorizations[ 0 ].authorization_id ]:
					mockAuthorizations[ 0 ],
				[ mockAuthorizations[ 1 ].authorization_id ]:
					mockAuthorizations[ 1 ],
			},
			summary: {},
		} );
	} );
} );
