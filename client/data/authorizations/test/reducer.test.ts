/** @format */
/**
 * External dependencies
 */
import type { Query } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import reducer from '../reducer';
import types from '../action-types';
import { getResourceId } from 'utils/data';
import { Authorization } from 'wcpay/types/authorizations';
import authorizationsFixture from './authorizations.fixture.json';
import authorizationsSummaryFixture from './authorizations-summary.fixture.json';

describe( 'Authorizations reducer tests', () => {
	const mockQuery: Query = { paged: '2', per_page: '50' };
	const mockAuthorizations = authorizationsFixture;
	const mockSummary = authorizationsSummaryFixture;

	const emptyState = { summary: {}, byId: {} };
	const filledState = {
		[ getResourceId( mockQuery ) ]: {
			data: mockAuthorizations,
		},
		summary: {
			[ getResourceId( mockQuery ) ]: {
				data: mockSummary,
			},
		},
		byId: {},
	};

	test( 'Unrelated action is ignored', () => {
		const mockAction = {
			type: 'WRONG-TYPE',
			data: mockAuthorizations.slice( 0 ),
			query: {},
		};

		expect( reducer( emptyState, mockAction ) ).toBe( emptyState );
		expect( reducer( filledState, mockAction ) ).toBe( filledState );
	} );

	test( 'New authorizations reduced correctly', () => {
		// Set up mock data
		const expected = {
			...emptyState,
			[ getResourceId( mockQuery ) ]: {
				data: mockAuthorizations,
			},
			byId: {},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_AUTHORIZATIONS,
			data: mockAuthorizations,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Authorizations updated correctly on updated info', () => {
		const newAuthorizations: Authorization[] = [
			...mockAuthorizations,
			...mockAuthorizations,
		];

		const expected = {
			...filledState,
			[ getResourceId( mockQuery ) ]: {
				data: newAuthorizations,
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_AUTHORIZATIONS,
			data: newAuthorizations,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'New authorizations summary reduced correctly', () => {
		const expected = {
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: mockSummary,
				},
			},
			byId: {},
		};

		const reduced = reducer( emptyState, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			data: mockSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'Authorizations summary updated correctly on updated info', () => {
		const newSummary = {
			total: 5000,
		};

		const expected = {
			...filledState,
			summary: {
				[ getResourceId( mockQuery ) ]: {
					data: newSummary,
				},
			},
		};

		const reduced = reducer( filledState, {
			type: types.SET_AUTHORIZATIONS_SUMMARY,
			data: newSummary,
			query: mockQuery,
		} );
		expect( reduced ).toStrictEqual( expected );
	} );

	test( 'should reduce new authorization correctly', () => {
		const stateAfterOne = reducer( undefined, {
			type: types.SET_AUTHORIZATION,
			data: mockAuthorizations[ 0 ],
		} );

		expect( stateAfterOne ).toStrictEqual( {
			byId: {
				[ mockAuthorizations[ 0 ].payment_intent_id ]:
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
				[ mockAuthorizations[ 0 ].payment_intent_id ]:
					mockAuthorizations[ 0 ],
				[ mockAuthorizations[ 1 ].payment_intent_id ]:
					mockAuthorizations[ 1 ],
			},
			summary: {},
		} );
	} );
} );
